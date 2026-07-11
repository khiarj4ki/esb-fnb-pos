<?php
$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

if (file_exists(__DIR__ . '/timezone.php')) {
    $timeZone = require __DIR__ . '/timezone.php';
} else {
    $timeZone = 'Asia/Jakarta';
}

$config = [
    'id' => 'esb-pos-ws',
    'timeZone' => $timeZone,
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset'
    ],
    'modules' => [
        'v1' => [
            'class' => 'app\modules\v1\Module'
        ],
        'external' => [
            'class' => 'app\modules\external\Module'
        ],
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'ESBPOSWSq7zf96s1uXQlpiQvlGsVZPexS21dyDKU'
        ],
        'i18n' => [
            'translations' => [
                'app*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'sourceLanguage' => 'en-US',
                    'basePath' => '@app/messages',
                    'fileMap' => [
                        'app' => 'app.php',
                    ],
                ],
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache'
        ],
        'user' => [
            'identityClass' => 'app\models\PosUser',
            'enableAutoLogin' => true
        ],
        'session' => [
            'name' => 'ESBPOSWS$7a87asd9WUadLkDhjs3D9d8'
        ],
        'errorHandler' => [
            'errorAction' => 'site/error'
        ],
        /*
          'mailer' => [
          'class' => 'yii\swiftmailer\Mailer',
          // send all mails to a file by default. You have to set
          // 'useFileTransport' to false and configure a transport
          // for the mailer to send real emails.
          'useFileTransport' => true,
          ],
         */
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning']
                ]
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'external/boga/get-table/<branch>' => 'external/boga/get-table',
                'external/boga/get-outstanding-by-table/<branch>/<table>/<date>' => 'external/boga/get-outstanding-by-table',
                'external/boga/get-outstanding-by-order/<salesnum>' => 'external/boga/get-outstanding-by-order',
                'external/boga/get-billed-order/<salesnum>' => 'external/boga/get-billed-order',
                '<module:\w+>/<controller:\w+>/<id:\d+>' => '<module>/<controller>/view',
                '<module:\w+>/<controller:\w+>/<action:\w+>/<id:\w+>' => '<module>/<controller>/<action>',
                '<module:\w+>/<controller:\w+>/<action:\w+>' => '<module>/<controller>/<action>'
            ]
        ]
    ],
    'params' => $params
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
    $config['modules']['debug']['panels']['user'] = false;

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
