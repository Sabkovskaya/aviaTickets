<?php

namespace app\controllers\api;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use app\models\Flight;
use app\models\Airport;

/**
 * FlightsController handles flight search
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

        return $behaviors;
    }

    /**
     * Search flights
     */
    public function actionSearch()
    {
        $from = Yii::$app->request->get('from', '');
        $to = Yii::$app->request->get('to', '');
        $date = Yii::$app->request->get('date', '');
        $passengers = (int)Yii::$app->request->get('passengers', 1);
        $category = Yii::$app->request->get('category', '');

        // Validate date format
        if (!empty($date)) {
            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
                Yii::$app->response->statusCode = 400;
                return [
                    'error' => [
                        'code' => 400,
                        'message' => 'Invalid date format',
                    ],
                ];
            }
        }

        $query = Flight::find()
            ->joinWith(['departureAirport', 'arrivalAirport'])
            ->where(['>=', 'flights.seats_available', $passengers]);

        // Filter by departure city
        if (!empty($from)) {
            $departureAirports = Airport::findByCity($from);
            if (!empty($departureAirports)) {
                $departureIds = array_map(function($airport) {
                    return $airport->id;
                }, $departureAirports);
                $query->andWhere(['in', 'flights.departure_airport_id', $departureIds]);
            } else {
                // No airports found, return empty result
                return [
                    'flights' => [],
                    'total' => 0,
                ];
            }
        }

        // Filter by arrival city
        if (!empty($to)) {
            $arrivalAirports = Airport::findByCity($to);
            if (!empty($arrivalAirports)) {
                $arrivalIds = array_map(function($airport) {
                    return $airport->id;
                }, $arrivalAirports);
                $query->andWhere(['in', 'flights.arrival_airport_id', $arrivalIds]);
            } else {
                // No airports found, return empty result
                return [
                    'flights' => [],
                    'total' => 0,
                ];
            }
        }

        // Filter by date
        if (!empty($date)) {
            $query->andWhere(['>=', 'flights.departure_time', $date . ' 00:00:00']);
            $query->andWhere(['<', 'flights.departure_time', $date . ' 23:59:59']);
        }

        // Filter by category
        if (!empty($category) && in_array($category, [Flight::CATEGORY_ECONOMY, Flight::CATEGORY_BASIC, Flight::CATEGORY_BUSINESS])) {
            $query->andWhere(['flights.category' => $category]);
        }

        $flights = $query->all();

        $result = [];
        foreach ($flights as $flight) {
            $result[] = [
                'id' => $flight->id,
                'flight_number' => $flight->flight_number,
                'from' => $flight->departureAirport->city . ' (' . $flight->departureAirport->code . ')',
                'to' => $flight->arrivalAirport->city . ' (' . $flight->arrivalAirport->code . ')',
                'departure_time' => date('c', strtotime($flight->departure_time)),
                'arrival_time' => date('c', strtotime($flight->arrival_time)),
                'price' => (float)$flight->price,
                'available_seats' => $flight->seats_available,
                'category' => $flight->category,
            ];
        }

        return [
            'flights' => $result,
            'total' => count($result),
        ];
    }
}

