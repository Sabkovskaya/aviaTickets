<?php

namespace app\controllers\api;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\ConflictHttpException;
use yii\web\UnprocessableEntityHttpException;
use yii\web\UnauthorizedHttpException;
use app\models\User;
use app\models\TokenBlacklist;
use app\components\JwtHelper;
use app\filters\JwtAuth;

/**
 * AuthController handles authentication
 */
class AuthController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON;
        
        // Only logout requires authentication
        $behaviors['authenticator'] = [
            'class' => JwtAuth::class,
            'except' => ['register', 'login'],
        ];

        return $behaviors;
    }

    /**
     * Register new user
     */
    public function actionRegister()
    {
        $data = Yii::$app->request->getBodyParams();

        $user = new User();
        $user->first_name = $data['first_name'] ?? '';
        $user->last_name = $data['last_name'] ?? '';
        $user->phone = $data['phone'] ?? '';
        $user->document_number = $data['document_number'] ?? '';
        
        // Validate password
        $password = $data['password'] ?? '';
        if (empty($password)) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => [
                        'password' => ['Required'],
                    ],
                ],
            ];
        }
        
        if (strlen($password) < 8) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => [
                        'password' => ['Too short'],
                    ],
                ],
            ];
        }
        
        $user->setPassword($password);

        if (!$user->validate()) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $user->errors,
                ],
            ];
        }

        // Check if phone already exists
        if (User::findByPhone($user->phone)) {
            Yii::$app->response->statusCode = 409;
            return [
                'error' => [
                    'code' => 409,
                    'message' => 'Phone already in use',
                ],
            ];
        }

        if ($user->save()) {
            Yii::$app->response->statusCode = 204;
            return null;
        }

        Yii::$app->response->statusCode = 422;
        return [
            'error' => [
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $user->errors,
            ],
        ];
    }

    /**
     * Login user
     */
    public function actionLogin()
    {
        $data = Yii::$app->request->getBodyParams();
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($phone) || empty($password)) {
            Yii::$app->response->statusCode = 422;
            return [
                'error' => [
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => [
                        'phone' => empty($phone) ? ['Required'] : [],
                        'password' => empty($password) ? ['Required'] : [],
                    ],
                ],
            ];
        }

        $user = User::findByPhone($phone);
        if (!$user || !$user->validatePassword($password)) {
            Yii::$app->response->statusCode = 401;
            return [
                'error' => [
                    'code' => 401,
                    'message' => 'Invalid credentials',
                ],
            ];
        }

        $token = JwtHelper::generateToken(['user_id' => $user->id], 3600);

        return [
            'token' => $token,
            'expires_in' => 3600,
        ];
    }

    /**
     * Logout user
     */
    public function actionLogout()
    {
        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');
        if (preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = JwtHelper::verifyToken($token);
            
            if ($payload && isset($payload['user_id'], $payload['exp'])) {
                TokenBlacklist::add($token, $payload['user_id'], date('Y-m-d H:i:s', $payload['exp']));
            }
        }

        Yii::$app->response->statusCode = 204;
        return null;
    }
}

