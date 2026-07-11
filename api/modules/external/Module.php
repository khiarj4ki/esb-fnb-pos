<?php
namespace app\modules\external;

use Yii;
use yii\web\Response;

/**
 * external module definition class
 */
class Module extends \yii\base\Module {
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\external\controllers';

    /**
     * {@inheritdoc}
     */
    public function init() {
        parent::init();

        Yii::$app->setComponents([
            'request' => [
                'class' => 'yii\web\Request',
                'enableCookieValidation' => false,
                'enableCsrfValidation' => false,
                'parsers' => [
                    'application/json' => 'yii\web\JsonParser'
                ]
            ],
            'response' => [
                'class' => 'yii\web\Response',
                'format' => Response::FORMAT_JSON
            ]
        ]);
    }

}
