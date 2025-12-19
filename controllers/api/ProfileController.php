<?php

namespace app\controllers\api;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\UnprocessableEntityHttpException;
use yii\web\UnsupportedMediaTypeHttpException;
use yii\web\BadRequestHttpException;
use app\filters\JwtAuth;
use app\models\User;

/**
 * ProfileController handles user profile operations
 */
class ProfileController extends Controller
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

        return $behaviors;
    }

    /**
     * Get user profile
     */
    public function actionIndex()
    {
        $user = Yii::$app->user->identity;

        return [
            'profile' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'document_number' => $user->document_number,
                'photo_url' => $user->photo_url,
            ],
        ];
    }

    /**
     * Update user profile (PATCH)
     */
    public function actionUpdate()
    {
        $user = Yii::$app->user->identity;
        $data = Yii::$app->request->getBodyParams();

        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        if (isset($data['document_number'])) {
            $user->document_number = $data['document_number'];
        }

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

        // Check if phone is already in use by another user
        if (isset($data['phone'])) {
            $existingUser = User::findByPhone($data['phone']);
            if ($existingUser && $existingUser->id !== $user->id) {
                Yii::$app->response->statusCode = 422;
                return [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            'phone' => ['Already in use'],
                        ],
                    ],
                ];
            }
        }

        if ($user->save()) {
            return [
                'profile' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone' => $user->phone,
                    'document_number' => $user->document_number,
                    'photo_url' => $user->photo_url,
                ],
            ];
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
     * Upload profile photo
     */
    public function actionPhoto()
    {
        $user = Yii::$app->user->identity;
        $file = \yii\web\UploadedFile::getInstanceByName('photo');

        if (!$file) {
            Yii::$app->response->statusCode = 400;
            return [
                'error' => [
                    'code' => 400,
                    'message' => 'No file uploaded',
                ],
            ];
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file->type, $allowedTypes)) {
            Yii::$app->response->statusCode = 415;
            return [
                'error' => [
                    'code' => 415,
                    'message' => 'Invalid file type. Only JPEG, PNG, GIF allowed',
                ],
            ];
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->size > $maxSize) {
            Yii::$app->response->statusCode = 413;
            return [
                'error' => [
                    'code' => 413,
                    'message' => 'File size exceeds 5MB limit',
                ],
            ];
        }

        // Create upload directory if not exists
        $uploadDir = Yii::getAlias('@webroot/media/users');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old photo if exists
        if ($user->photo_url && file_exists(Yii::getAlias('@webroot' . $user->photo_url))) {
            unlink(Yii::getAlias('@webroot' . $user->photo_url));
        }

        // Generate filename
        $extension = $file->extension;
        $filename = $user->id . '_photo.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        $url = '/media/users/' . $filename;

        if ($file->saveAs($filepath)) {
            $user->photo_url = $url;
            $user->save(false);

            return [
                'photo_url' => $url,
                'message' => 'Photo uploaded successfully',
            ];
        }

        Yii::$app->response->statusCode = 500;
        return [
            'error' => [
                'code' => 500,
                'message' => 'Failed to upload photo',
            ],
        ];
    }
}

