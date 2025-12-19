<?php

namespace app\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * Admin Authorization Filter
 */
class AdminAuth extends ActionFilter
{
    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (Yii::$app->user->isGuest) {
            throw new \yii\web\UnauthorizedHttpException('Authentication required');
        }

        if (!Yii::$app->user->identity->isAdmin()) {
            throw new ForbiddenHttpException('Admin rights required');
        }

        return parent::beforeAction($action);
    }
}

