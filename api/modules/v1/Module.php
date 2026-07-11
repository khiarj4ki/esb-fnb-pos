<?php
namespace app\modules\v1;

use app\modules\v1\Member\MemberID\MemberIDProvider;
use Yii;
use yii\web\Response;

/**
 * v1 module definition class
 */
class Module extends \yii\base\Module {
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\v1\controllers';

    /**
     * {@inheritdoc}
     */
    public function init() {
        parent::init();

        Yii::$app->setComponents([
            'user' => [
                'class' => 'yii\web\User',
                'identityClass' => 'app\models\PosUser'
            ],
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

        $this->register();
    }

    protected function register()
    {
        MemberIDProvider::register();
    }

}
