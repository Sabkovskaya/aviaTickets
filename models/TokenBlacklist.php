<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * TokenBlacklist model
 *
 * @property integer $id
 * @property string $token
 * @property integer $user_id
 * @property string $expires_at
 * @property integer $created_at
 *
 * @property User $user
 */
class TokenBlacklist extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'token_blacklist';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['token', 'user_id', 'expires_at'], 'required'],
            [['user_id'], 'integer'],
            [['expires_at'], 'safe'],
            [['token'], 'string', 'max' => 500],
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
     * Check if token is blacklisted
     *
     * @param string $token
     * @return bool
     */
    public static function isBlacklisted($token)
    {
        return static::find()
            ->where(['token' => $token])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->exists();
    }

    /**
     * Add token to blacklist
     *
     * @param string $token
     * @param int $userId
     * @param string $expiresAt
     * @return bool
     */
    public static function add($token, $userId, $expiresAt)
    {
        $model = new static();
        $model->token = $token;
        $model->user_id = $userId;
        $model->expires_at = $expiresAt;
        return $model->save();
    }

    /**
     * Clean expired tokens
     */
    public static function cleanExpired()
    {
        static::deleteAll(['<', 'expires_at', date('Y-m-d H:i:s')]);
    }
}

