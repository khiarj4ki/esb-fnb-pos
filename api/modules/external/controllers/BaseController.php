<?php
namespace app\modules\external\controllers;

use app\components\CustomCors;
use app\models\PosUser;
use Yii;
use yii\filters\auth\HttpBasicAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\Request;

/**
 * @property Request $request
 */
class BaseController extends Controller {
    public $request;

    public function __construct($id, $module, $config = []) {
        parent::__construct($id, $module, $config);
        $this->request = Yii::$app->request;
    }

    public function behaviors() {
        $behaviors = parent::behaviors();
        $auth = $behaviors['authenticator'];
        unset($behaviors['authenticator']);

        $behaviors['corsFilter'] = [
            'class' => CustomCors::class,
        ];
        
        $behaviors['verbs'] = [
            'class' => VerbFilter::className(),
            'actions' => [
                '*' => ['POST']
            ],
        ];

        $behaviors['authenticator'] = $auth;
        $behaviors['authenticator'] = [
            'class' => HttpBasicAuth::class,
            'auth' => function ($username, $password) {
                if ($username == Yii::$app->params['externalUsername'] && 
                        $password == Yii::$app->params['externalPassword']) {
                    $user = new PosUser();
                    $user->username = $username;
                    return $user;
                } else {
                    return false;
                }
            }
        ];
        
        return $behaviors;
    }
}
