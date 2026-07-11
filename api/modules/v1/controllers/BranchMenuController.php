<?php
namespace app\modules\v1\controllers;

use app\models\forms\BranchMenuSetting;
use app\models\BranchMenu;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class BranchMenuController extends BaseController {
    public function behaviors() 
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            [
                'index'
            ]
        );
        return $behaviors;
    }

    public function actionIndex() {
        ini_set('memory_limit', '-1');
        
        $data = $this->request->post();
        if (!$data) {
            throw new HttpException(400);
        }
        return BranchMenu::findActiveBranchMenu($data['showLimitInfo'], $data['showSoldOutInfo']);
    }

    public function actionSave() {
        if (!$this->request->post('branchMenu')) {
            throw new HttpException(400);
        }

        $branchMenuModel = new BranchMenuSetting([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$branchMenuModel->save()) {
                throw new Exception(json_encode($branchMenuModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

}
