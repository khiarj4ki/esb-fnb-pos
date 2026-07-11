<?php

namespace app\modules\v1\controllers;

use app\components\AppHelper;
use app\components\CustomCors;
use app\models\PosUser;
use app\models\Setting;
use Yii;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBasicAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\Request;
use yii\web\UnauthorizedHttpException;

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

        $behaviors['authenticator'] = $auth;
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                HttpBearerAuth::class,
                [
                    'class' => HttpBasicAuth::class,
                    'auth' => function ($username, $password) {
                        $authUsername = Yii::$app->params['restUsername'];
                        $authPassword = Yii::$app->params['restPassword'];
                        if ($username == $authUsername && $password == $authPassword) {
                            $posUser = new PosUser();
                            return $posUser;
                        } else {
                            return null;
                        }
                    }
                ]
            ],
            // Handle CORS preflight
            'except' => []
        ];
        return $behaviors;
    }

    public function beforeAction($action) {
        $token = str_replace('Bearer ', '',
            Yii::$app->request->headers->get('authorization'));
        if (strpos(strtolower($token), 'basic') === 0) {
            $token = null;
        }

        $controller = Yii::$app->controller->id;
        $actionUrl = Yii::$app->controller->action->id;
        $url = $controller . "/" . $actionUrl;
        
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');
        if ($trialMode) {
            $currentConnection = Yii::$app->db;
            $dbName = AppHelper::getDsnAttribute('dbname', $currentConnection->dsn);
            $dbHost = AppHelper::getDsnAttribute('host', $currentConnection->dsn);

            if ($trialMode->value1 == '1') {
                if (strpos($dbName, '_trial') === false) $trialDbName = $dbName . "_trial";
                $trialDbDsn = "mysql:host=$dbHost;dbname=$trialDbName";
                if ($currentConnection->dsn != $trialDbDsn) {
                    $currentConnection->close();
                    $currentConnection->dsn = $trialDbDsn;
                    $currentConnection->open();

                    $ezoFsApi = Setting::getSelfOrderSetting('EZO FS API Url');
                    $ezoQsApi = Setting::getSelfOrderSetting('EZO TA API Url');
                    if ($ezoFsApi && $ezoQsApi) {
                        $currentConnection->createCommand()->update(
                            Setting::tableName(),
                            [ 'value1' =>  NULL ],
                            ['key1' => 'Local Setting', 'key2' => ['EZO FS API Url', 'EZO TA API Url']]
                        )->execute();
                    }
                }
            } else {
                if (strpos($dbName, '_trial') !== false) $dbName = str_replace('_trial', '', $dbName);
                $prodDbDsn = "mysql:host=$dbHost;dbname=$dbName";
                if ($currentConnection->dsn != $prodDbDsn) {
                    $currentConnection->close();
                    $currentConnection->dsn = $prodDbDsn;
                    $currentConnection->open();
                }
            }

        }

        //@Notes: Cek jika url yang diakses tidak memerlukan bearer
        //@Notes: User masih dianggap Guest di dalam Before Action
        $hasAccess = AppHelper::hasAccess($url, $token);
        if ($hasAccess) {
            
        } else {
            throw new UnauthorizedHttpException();
        }

        return parent::beforeAction($action);
    }

}
