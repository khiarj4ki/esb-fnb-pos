<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

if (file_exists(__DIR__ . '/timezone.php')) {
    $timeZone = require __DIR__ . '/timezone.php';
} else {
    $timeZone = 'Asia/Jakarta';
}

$config = [
    'id' => 'basic-console',
    'timeZone' => $timeZone,
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'user' => [
            'class' => 'yii\web\User',
            'identityClass' => 'app\models\PosUser'
        ],
    ],
    'params' => $params,
    'controllerMap' => [
        'migrate' => [ 
            'class' => 'app\commands\MigrationController',
            'migrationPath' => '@app/migrations',
        ],
    ],
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
