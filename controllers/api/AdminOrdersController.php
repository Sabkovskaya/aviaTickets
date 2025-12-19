<?php

namespace app\controllers\api;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use app\filters\JwtAuth;
use app\filters\AdminAuth;
use app\models\Order;
use app\models\Ticket;
use app\models\Flight;

/**
 * Admin OrdersController handles order management
 */
class AdminOrdersController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON;
        $behaviors['authenticator'] = [
            'class' => JwtAuth::class,
        ];
        $behaviors['admin'] = [
            'class' => AdminAuth::class,
        ];

        return $behaviors;
    }

    /**
     * Get all orders
     */
    public function actionIndex()
    {
        $orders = Order::find()
            ->with(['user', 'tickets'])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $result = [];
        foreach ($orders as $order) {
            $result[] = [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'user_name' => $order->user ? ($order->user->first_name . ' ' . $order->user->last_name) : null,
                'status' => $order->status,
                'total' => (float)$order->total,
                'refund_status' => $order->refund_status,
                'created_at' => date('c', strtotime($order->created_at)),
                'tickets_count' => count($order->tickets),
            ];
        }

        return [
            'orders' => $result,
            'total' => count($result),
        ];
    }

    /**
     * Get available statuses for order
     * 
     * @param Order $order
     * @return array
     */
    private function getAvailableStatuses($order)
    {
        $transitions = [
            Order::STATUS_PENDING => [Order::STATUS_PAID, Order::STATUS_CANCELLED],
            Order::STATUS_PAID => [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED],
            Order::STATUS_CANCELLED => [],
            Order::STATUS_REFUNDED => [],
        ];

        return $transitions[$order->status] ?? [];
    }

    /**
     * Update order status
     */
    public function actionUpdate($id)
    {
        $order = Order::findOne($id);
        if (!$order) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Order not found',
                ],
            ];
        }

        $data = Yii::$app->request->getBodyParams();
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            $availableStatuses = $this->getAvailableStatuses($order);
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => [
                        'status' => ['Required'],
                    ],
                    'available_statuses' => $availableStatuses,
                    'current_status' => $order->status,
                    'note' => 'Available statuses for current status "' . $order->status . '": ' . implode(', ', $availableStatuses),
                ],
            ];
        }

        // Check if status transition is valid
        if (!$order->canTransitionTo($newStatus)) {
            $availableStatuses = $this->getAvailableStatuses($order);
            Yii::$app->response->statusCode = 400;
            return [
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid status transition',
                    'current_status' => $order->status,
                    'requested_status' => $newStatus,
                    'available_statuses' => $availableStatuses,
                    'note' => 'Available statuses for current status "' . $order->status . '": ' . implode(', ', $availableStatuses),
                ],
            ];
        }

        $refund = $data['refund'] ?? false;

        // If cancelling paid order, process refund
        if ($newStatus === Order::STATUS_CANCELLED && $order->status === Order::STATUS_PAID && $refund) {
            $order->status = Order::STATUS_CANCELLED;
            $order->refund_status = 'processed';

            // Release seats
            foreach ($order->tickets as $ticket) {
                if ($ticket->flight) {
                    $ticket->flight->releaseSeats(1);
                }
            }
        } else {
            $order->status = $newStatus;
        }

        if ($order->save()) {
            return [
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'refund_status' => $order->refund_status,
                ],
            ];
        }

        Yii::$app->response->statusCode = 422;
        return [
            'error' => [
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $order->errors,
            ],
        ];
    }

    /**
     * Delete order
     */
    public function actionDelete($id)
    {
        $order = Order::findOne($id);
        if (!$order) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Order not found',
                ],
            ];
        }

        // Check if order has paid status - cannot delete paid orders
        if ($order->status === Order::STATUS_PAID) {
            Yii::$app->response->statusCode = 409;
            return [
                'error' => [
                    'code' => 409,
                    'message' => 'Cannot delete paid order. Cancel or refund it first.',
                ],
            ];
        }

        // Delete related tickets
        Ticket::deleteAll(['order_id' => $order->id]);

        // Delete order
        $order->delete();
        
        Yii::$app->response->statusCode = 204;
        return null;
    }
}

