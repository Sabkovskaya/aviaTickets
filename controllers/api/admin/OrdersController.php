<?php

namespace app\controllers\api\admin;

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
class OrdersController extends Controller
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
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => [
                        'status' => ['Required'],
                    ],
                ],
            ];
        }

        // Check if status transition is valid
        if (!$order->canTransitionTo($newStatus)) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid status transition',
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
}

