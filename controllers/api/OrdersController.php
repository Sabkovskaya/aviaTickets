<?php

namespace app\controllers\api;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use app\filters\JwtAuth;
use app\models\Order;
use app\models\Ticket;
use app\models\CartItem;
use app\models\Flight;

/**
 * OrdersController handles order operations
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

        return $behaviors;
    }

    /**
     * Get user orders
     */
    public function actionIndex()
    {
        $user = Yii::$app->user->identity;
        $orders = Order::find()
            ->where(['user_id' => $user->id])
            ->with('tickets')
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $result = [];
        foreach ($orders as $order) {
            $result[] = [
                'id' => $order->id,
                'status' => $order->status,
                'total' => (float)$order->total,
                'created_at' => date('c', strtotime($order->created_at)),
                'tickets_count' => count($order->tickets),
            ];
        }

        return [
            'orders' => $result,
        ];
    }

    /**
     * Checkout (create order from cart)
     */
    public function actionCheckout()
    {
        $user = Yii::$app->user->identity;
        $data = Yii::$app->request->getBodyParams();

        // Get cart items
        $cartItems = CartItem::find()
            ->where(['user_id' => $user->id])
            ->with('flight')
            ->all();

        if (empty($cartItems)) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => [
                    'code' => 400,
                    'message' => 'Cart is empty',
                ],
            ];
        }

        // Check if all flights still have available seats
        foreach ($cartItems as $item) {
            if (!$item->flight || !$item->flight->hasAvailableSeats(1)) {
                Yii::$app->response->statusCode = 409;
                return [
                    'error' => [
                        'code' => 409,
                        'message' => 'One or more seats no longer available',
                    ],
                ];
            }
        }

        // Calculate total
        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item->flight->price;
        }

        // Start transaction
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Create order
            $order = new Order();
            $order->user_id = $user->id;
            $order->status = Order::STATUS_PAID; // Simulate payment success
            $order->total = $total;

            if (!$order->save()) {
                throw new \Exception('Failed to create order');
            }

            // Create tickets and reserve seats
            $tickets = [];
            foreach ($cartItems as $item) {
                // Reserve seat
                if (!$item->flight->reserveSeats(1)) {
                    throw new \Exception('Failed to reserve seat');
                }

                // Create ticket
                $ticket = new Ticket();
                $ticket->order_id = $order->id;
                $ticket->flight_id = $item->flight_id;
                $ticket->ticket_number = Ticket::generateTicketNumber();
                $ticket->passenger_name = $item->passenger_name;

                if (!$ticket->save()) {
                    throw new \Exception('Failed to create ticket');
                }

                $tickets[] = [
                    'ticket_number' => $ticket->ticket_number,
                    'passenger' => $ticket->passenger_name,
                ];
            }

            // Clear cart
            CartItem::deleteAll(['user_id' => $user->id]);

            $transaction->commit();

            Yii::$app->response->statusCode = 201;
            return [
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'total' => (float)$order->total,
                    'tickets' => $tickets,
                    'created_at' => date('c', strtotime($order->created_at)),
                ],
            ];
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::$app->response->statusCode = 500;
            return [
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to create order',
                ],
            ];
        }
    }
}

