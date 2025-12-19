<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Order model
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $status
 * @property double $total
 * @property string $refund_status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property User $user
 * @property Ticket[] $tickets
 */
class Order extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'orders';
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
            [['user_id', 'total'], 'required'],
            [['user_id'], 'integer'],
            [['total'], 'number', 'min' => 0],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_REFUNDED]],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['refund_status'], 'string', 'max' => 50],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTickets()
    {
        return $this->hasMany(Ticket::class, ['order_id' => 'id']);
    }

    /**
     * Check if status transition is valid
     *
     * @param string $newStatus
     * @return bool
     */
    public function canTransitionTo($newStatus)
    {
        $transitions = [
            self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
            self::STATUS_PAID => [self::STATUS_CANCELLED, self::STATUS_REFUNDED],
            self::STATUS_CANCELLED => [],
            self::STATUS_REFUNDED => [],
        ];

        return isset($transitions[$this->status]) && in_array($newStatus, $transitions[$this->status]);
    }
}

