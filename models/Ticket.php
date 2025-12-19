<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Ticket model
 *
 * @property integer $id
 * @property integer $order_id
 * @property integer $flight_id
 * @property string $ticket_number
 * @property string $passenger_name
 * @property string $seat_number
 * @property integer $created_at
 *
 * @property Order $order
 * @property Flight $flight
 */
class Ticket extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tickets';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_id', 'flight_id', 'ticket_number', 'passenger_name'], 'required'],
            [['order_id', 'flight_id'], 'integer'],
            [['ticket_number'], 'string', 'max' => 50],
            [['ticket_number'], 'unique'],
            [['passenger_name'], 'string', 'max' => 200],
            [['seat_number'], 'string', 'max' => 10],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFlight()
    {
        return $this->hasOne(Flight::class, ['id' => 'flight_id']);
    }

    /**
     * Generate unique ticket number
     *
     * @return string
     */
    public static function generateTicketNumber()
    {
        do {
            $number = 'ETK-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::findOne(['ticket_number' => $number]));

        return $number;
    }
}

