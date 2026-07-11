<?php
namespace app\components;

use Yii;
use yii\filters\Cors;
use yii\web\Request;

/**
 * @property Request $request
 */
class CustomCors extends Cors {
    public $request;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->request = Yii::$app->request;
    }

    public function beforeAction($action) {
        parent::beforeAction($action);

        if ($this->request->isOptions) {
            Yii::$app->getResponse()->getHeaders()->set('Allow', 'POST GET PUT');
            Yii::$app->end();
        } else {
            if ($this->request->headers->has('Content-Language')) {
                Yii::$app->language = $this->request->headers->get('Content-Language');
            }
        }

        return true;
    }

}
