<?php

namespace app\components;

use Yii;
use yii\web\ErrorHandler;

/**
 * API Error Handler
 */
class ApiErrorHandler extends ErrorHandler
{
    /**
     * {@inheritdoc}
     */
    protected function renderException($exception)
    {
        if (Yii::$app->has('response')) {
            $response = Yii::$app->getResponse();
            $response->clear();
        } else {
            $response = new \yii\web\Response();
        }

        $response->format = \yii\web\Response::FORMAT_JSON;

        if ($exception instanceof \yii\web\HttpException) {
            $response->setStatusCode($exception->statusCode);
            $response->data = [
                'error' => [
                    'code' => $exception->statusCode,
                    'message' => $exception->getMessage() ?: $this->getStatusCodeMessage($exception->statusCode),
                ],
            ];
        } else {
            $response->setStatusCode(500);
            $response->data = [
                'error' => [
                    'code' => 500,
                    'message' => YII_DEBUG ? $exception->getMessage() : 'Internal server error',
                ],
            ];
        }

        $response->send();
    }

    /**
     * Get status code message
     *
     * @param int $statusCode
     * @return string
     */
    protected function getStatusCodeMessage($statusCode)
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            413 => 'Payload Too Large',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
        ];

        return $messages[$statusCode] ?? 'Error';
    }
}

