<?php

namespace app\controllers\api\admin;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\web\ConflictHttpException;
use app\filters\JwtAuth;
use app\filters\AdminAuth;
use app\models\Flight;
use app\models\Airport;
use app\models\Order;

/**
 * Admin FlightsController handles flight management
 */
class FlightsController extends Controller
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
     * Create flight
     */
    public function actionCreate()
    {
        $data = Yii::$app->request->getBodyParams();

        $flight = new Flight();
        $flight->flight_number = $data['flight_number'] ?? '';
        $flight->departure_time = $data['departure_time'] ?? '';
        $flight->arrival_time = $data['arrival_time'] ?? '';
        $flight->price = $data['price'] ?? 0;
        $flight->seats_total = $data['seats_total'] ?? 0;
        $flight->category = $data['category'] ?? Flight::CATEGORY_ECONOMY;

        // Find airports by code
        if (isset($data['departure_airport'])) {
            $departureAirport = Airport::findByCode($data['departure_airport']);
            if (!$departureAirport) {
                Yii::$app->response->statusCode = 422;
                return [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            'departure_airport' => ['Airport not found'],
                        ],
                    ],
                ];
            }
            $flight->departure_airport_id = $departureAirport->id;
        }

        if (isset($data['arrival_airport'])) {
            $arrivalAirport = Airport::findByCode($data['arrival_airport']);
            if (!$arrivalAirport) {
                Yii::$app->response->statusCode = 422;
                return [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            'arrival_airport' => ['Airport not found'],
                        ],
                    ],
                ];
            }
            $flight->arrival_airport_id = $arrivalAirport->id;
        }

        $flight->seats_available = $flight->seats_total;

        if (!$flight->validate()) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $flight->errors,
                ],
            ];
        }

        if ($flight->save()) {
            Yii::$app->response->statusCode = 201;
            return [
                'flight' => [
                    'id' => $flight->id,
                    'flight_number' => $flight->flight_number,
                    'departure_airport' => $flight->departureAirport->code,
                    'arrival_airport' => $flight->arrivalAirport->code,
                    'departure_time' => date('c', strtotime($flight->departure_time)),
                    'arrival_time' => date('c', strtotime($flight->arrival_time)),
                    'price' => (float)$flight->price,
                    'seats_total' => $flight->seats_total,
                    'seats_available' => $flight->seats_available,
                    'category' => $flight->category,
                ],
            ];
        }

        Yii::$app->response->statusCode = 422;
        return [
            'error' => [
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $flight->errors,
            ],
        ];
    }

    /**
     * Update flight
     */
    public function actionUpdate($id)
    {
        $flight = Flight::findOne($id);
        if (!$flight) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Flight not found',
                ],
            ];
        }

        $data = Yii::$app->request->getBodyParams();

        if (isset($data['price'])) {
            $flight->price = $data['price'];
        }
        if (isset($data['seats_total'])) {
            $flight->seats_total = $data['seats_total'];
            // Adjust seats_available if needed
            if ($flight->seats_available > $flight->seats_total) {
                $flight->seats_available = $flight->seats_total;
            }
        }
        if (isset($data['departure_time'])) {
            $flight->departure_time = $data['departure_time'];
        }
        if (isset($data['arrival_time'])) {
            $flight->arrival_time = $data['arrival_time'];
        }
        if (isset($data['category'])) {
            $flight->category = $data['category'];
        }

        if (!$flight->validate()) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $flight->errors,
                ],
            ];
        }

        if ($flight->save()) {
            return [
                'flight' => [
                    'id' => $flight->id,
                    'price' => (float)$flight->price,
                    'seats_total' => $flight->seats_total,
                    'seats_available' => $flight->seats_available,
                ],
            ];
        }

        Yii::$app->response->statusCode = 422;
        return [
            'error' => [
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $flight->errors,
            ],
        ];
    }

    /**
     * Delete flight
     */
    public function actionDelete($id)
    {
        $flight = Flight::findOne($id);
        if (!$flight) {
            Yii::$app->response->statusCode = 404;
            return [
                'error' => [
                    'code' => 404,
                    'message' => 'Flight not found',
                ],
            ];
        }

        // Check if flight has paid orders
        if ($flight->hasPaidOrders()) {
            Yii::$app->response->statusCode = 409;
            return [
                'error' => [
                    'code' => 409,
                    'message' => 'Cannot delete flight with paid orders',
                ],
            ];
        }

        // Delete related cart items
        \app\models\CartItem::deleteAll(['flight_id' => $flight->id]);

        $flight->delete();
        Yii::$app->response->statusCode = 204;
        return null;
    }
}

