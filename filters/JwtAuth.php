<?php

namespace app\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\UnauthorizedHttpException;
use app\components\JwtHelper;
use app\models\User;
use app\models\TokenBlacklist;

/**
 * JWT Authentication Filter
 */
class JwtAuth extends ActionFilter
{
    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        $request = Yii::$app->request;
        $authHeader = $request->getHeaders()->get('Authorization');

        if (!$authHeader) {
            throw new UnauthorizedHttpException('Authentication required');
        }

        if (preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            throw new UnauthorizedHttpException('Invalid authorization header');
        }

        // Check if token is blacklisted
        if (TokenBlacklist::isBlacklisted($token)) {
            throw new UnauthorizedHttpException('Token has been revoked');
        }

        // Verify token
        $payload = JwtHelper::verifyToken($token);
        if (!$payload || !isset($payload['user_id'])) {
            throw new UnauthorizedHttpException('Invalid or expired token');
        }

        // Load user
        $user = User::findIdentity($payload['user_id']);
        if (!$user) {
            throw new UnauthorizedHttpException('User not found');
        }

        // Set user identity
        Yii::$app->user->login($user);

        return parent::beforeAction($action);
    }
}

