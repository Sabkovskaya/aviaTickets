<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Flight model
 *
 * @property integer $id
 * @property string $flight_number
 * @property integer $departure_airport_id
 * @property integer $arrival_airport_id
 * @property string $departure_time
 * @property string $arrival_time
 * @property double $price
 * @property integer $seats_total
 * @property integer $seats_available
 * @property string $category
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Airport $departureAirport
 * @property Airport $arrivalAirport
 */
class Flight extends ActiveRecord
{
    const CATEGORY_ECONOMY = 'economy';
    const CATEGORY_BASIC = 'basic';
    const CATEGORY_BUSINESS = 'business';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'flights';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => new \yii\db\Expression('NOW()'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['flight_number', 'departure_airport_id', 'arrival_airport_id', 'departure_time', 'arrival_time', 'price', 'seats_total', 'seats_available'], 'required'],
            [['departure_airport_id', 'arrival_airport_id', 'seats_total', 'seats_available'], 'integer'],
            [['departure_time', 'arrival_time'], 'safe'],
            [['price'], 'number', 'min' => 0],
            [['flight_number'], 'string', 'max' => 20],
            [['category'], 'in', 'range' => [self::CATEGORY_ECONOMY, self::CATEGORY_BASIC, self::CATEGORY_BUSINESS]],
            [['category'], 'default', 'value' => self::CATEGORY_ECONOMY],
            [['seats_available'], 'integer', 'min' => 0],
            [['seats_available'], 'validateSeatsAvailable'],
        ];
    }

    /**
     * Validate seats available
     */
    public function validateSeatsAvailable($attribute, $params)
    {
        if ($this->seats_available > $this->seats_total) {
            $this->addError($attribute, 'Available seats cannot exceed total seats');
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDepartureAirport()
    {
        return $this->hasOne(Airport::class, ['id' => 'departure_airport_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getArrivalAirport()
    {
        return $this->hasOne(Airport::class, ['id' => 'arrival_airport_id']);
    }

    /**
     * Check if flight has available seats
     *
     * @param int $count
     * @return bool
     */
    public function hasAvailableSeats($count = 1)
    {
        return $this->seats_available >= $count;
    }

    /**
     * Reserve seats
     *
     * @param int $count
     * @return bool
     */
    public function reserveSeats($count = 1)
    {
        if (!$this->hasAvailableSeats($count)) {
            return false;
        }
        $this->seats_available -= $count;
        return $this->save(false);
    }

    /**
     * Release seats
     *
     * @param int $count
     * @return bool
     */
    public function releaseSeats($count = 1)
    {
        $this->seats_available += $count;
        if ($this->seats_available > $this->seats_total) {
            $this->seats_available = $this->seats_total;
        }
        return $this->save(false);
    }

    /**
     * Check if flight has paid orders
     *
     * @return bool
     */
    public function hasPaidOrders()
    {
        return Order::find()
            ->innerJoinWith('tickets')
            ->where(['tickets.flight_id' => $this->id])
            ->andWhere(['orders.status' => Order::STATUS_PAID])
            ->exists();
    }
}

