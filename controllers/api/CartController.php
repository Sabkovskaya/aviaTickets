<?php

namespace app\controllers\api;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\filters\JwtAuth;
use app\models\CartItem;
use app\models\Flight;

/**
 * CartController handles shopping cart operations
 */
class CartController extends Controller
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
     * Get cart items
     */
    public function actionIndex()
    {
        $user = Yii::$app->user->identity;
        $items = CartItem::find()
            ->where(['user_id' => $user->id])
            ->with('flight')
            ->all();

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->id,
                'flight_id' => $item->flight_id,
                'passenger_name' => $item->passenger_name,
                'flight' => $item->flight ? [
                    'flight_number' => $item->flight->flight_number,
                    'departure_time' => date('c', strtotime($item->flight->departure_time)),
                    'price' => (float)$item->flight->price,
                ] : null,
            ];
        }

        return [
            'items' => $result,
            'total' => count($result),
        ];
    }

    /**
     * Add item to cart
     */
    public function actionCreate()
    {
        $user = Yii::$app->user->identity;
        $data = Yii::$app->request->getBodyParams();

        $flightId = $data['flight_id'] ?? null;
        $passengerName = $data['passenger_name'] ?? '';

        if (!$flightId) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => [
                        'flight_id' => ['Required'],
                    ],
                ],
            ];
        }

        $flight = Flight::findOne($flightId);
        if (!$flight) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Flight not found',
                ],
            ];
        }

        if (!$flight->hasAvailableSeats(1)) {
            Yii::$app->response->statusCode = 409;
            return [
                'error' => [
                    'code' => 409,
                    'message' => 'No available seats',
                ],
            ];
        }

        $cartItem = new CartItem();
        $cartItem->user_id = $user->id;
        $cartItem->flight_id = $flightId;
        $cartItem->passenger_name = $passengerName ?: $user->getFullName();

        if ($cartItem->save()) {
            Yii::$app->response->statusCode = 201;
            return [
                'id' => $cartItem->id,
                'flight_id' => $cartItem->flight_id,
                'passenger_name' => $cartItem->passenger_name,
            ];
        }

        Yii::$app->response->statusCode = 422;
        return [
            'error' => [
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $cartItem->errors,
            ],
        ];
    }

    /**
     * Delete item from cart
     */
    public function actionDelete($id)
    {
        $user = Yii::$app->user->identity;
        $item = CartItem::findOne(['id' => $id, 'user_id' => $user->id]);

        if (!$item) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Item not found',
                ],
            ];
        }

        $item->delete();
        Yii::$app->response->statusCode = 204;
        return null;
    }
}

