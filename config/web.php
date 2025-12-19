<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\controllers',
    'controllerMap' => [
        'admin-flights' => 'app\controllers\api\AdminFlightsController',
        'admin-orders' => 'app\controllers\api\AdminOrdersController',
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'avia_123$$$',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'class' => 'app\components\ApiErrorHandler',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                // Auth routes
                'POST api/auth/register' => 'api/auth/register',
                'POST api/auth/login' => 'api/auth/login',
                'POST api/auth/logout' => 'api/auth/logout',
                // Profile routes
                'GET api/profile' => 'api/profile/index',
                'PATCH api/profile' => 'api/profile/update',
                'POST api/profile/photo' => 'api/profile/photo',
                // Flights routes
                'GET api/flights/search' => 'api/flights/search',
                // Cart routes
                'GET api/cart' => 'api/cart/index',
                'POST api/cart' => 'api/cart/create',
                'DELETE api/cart/<id:\d+>' => 'api/cart/delete',
                // Orders routes
                'GET api/orders' => 'api/orders/index',
                'POST api/orders/checkout' => 'api/orders/checkout',
                // Admin flights routes - используем дефис для соответствия имени контроллера
                'GET api/admin/flights' => 'api/admin-flights/index',
                'POST api/admin/flights' => 'api/admin-flights/create',
                'PATCH api/admin/flights/<id:\d+>' => 'api/admin-flights/update',  
                'DELETE api/admin/flights/<id:\d+>' => 'api/admin-flights/delete',
                // Admin orders routes
                'GET api/admin/orders' => 'api/admin-orders/index',
                'PATCH api/admin/orders/<id:\d+>' => 'api/admin-orders/update',
                'DELETE api/admin/orders/<id:\d+>' => 'api/admin-orders/delete',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
