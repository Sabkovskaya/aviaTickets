<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Airport model
 *
 * @property integer $id
 * @property string $code
 * @property string $name
 * @property string $city
 * @property integer $created_at
 */
class Airport extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'airports';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code', 'name', 'city'], 'required'],
            [['code'], 'string', 'max' => 10],
            [['code'], 'unique'],
            [['name'], 'string', 'max' => 255],
            [['city'], 'string', 'max' => 100],
        ];
    }

    /**
     * Find airport by code
     *
     * @param string $code
     * @return static|null
     */
    public static function findByCode($code)
    {
        return static::findOne(['code' => $code]);
    }

    /**
     * Find airport by city name
     *
     * @param string $city
     * @return static[]
     */
    public static function findByCity($city)
    {
        return static::find()->where(['like', 'city', $city])->all();
    }
}

