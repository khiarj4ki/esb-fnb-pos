<?php

namespace app\modules\v1\controllers;

use app\models\forms\UpdatePos;
use app\models\PosVersion;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class PosUpdateController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [ 'index', 'pos-update-file']
        );
        return $behaviors;
    }

    public function actionIndex()
    {
        try {
            $hasNewVersion = UpdatePos::hasNewVersion();

            if ($hasNewVersion) {
                $latestVersion = PosVersion::getLatestVersion();
            } else {
                $latestVersion = '';
            }

            return $latestVersion;
        } catch (Exception $ex) {
            throw new HttpException(
                500,
                Yii::t('app', 'Failed to check updates')
            );
        }
    }

    public function actionCheckVersion()
    {
        $hasNewVersion = UpdatePos::hasNewVersion();

        if ($hasNewVersion) {
            $latestVersion = PosVersion::getLatestVersion();
        } else {
            $latestVersion = null;
        }

        return $latestVersion;
    }

    public function actionApplyUpdate()
    {
        $this->validatePost();
        try {
            $updatePosModel = new UpdatePos();
            $updatePosModel->startUpdate = date('Y-m-d H:i:s');
            if (!$updatePosModel->downloadUpdate()) {
                throw new HttpException(
                    408,
                    Yii::t('app', 'Failed to download updates')
                );
            }

            $updatePosModel->attributes = $this->request->post();
            if (!$updatePosModel->applyUpdate()) {
                throw new HttpException(
                    404,
                    Yii::t('app', 'Failed to apply updates')
                );
            }
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }


    public function actionApplyUpdateKiosk()
    {
        $this->validatePost();
        try {
            $updateKioskModel = new UpdatePos();
            $updateKioskModel->attributes = $this->request->post();
            $updateKioskModel->startUpdate = date('Y-m-d H:i:s');
            $updateKioskModel->pathFolder = "/esb-kiosk";
            if (!$updateKioskModel->downloadUpdateKiosk()) {
                throw new HttpException(
                    400,
                    Yii::t('app', 'Failed to download updates')
                );
            }

            if (!$updateKioskModel->applyUpdateKiosk()) {
                throw new HttpException(
                    400,
                    Yii::t('app', 'Failed to apply updates')
                );
            }
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionApplyUpdateOds()
    {
        $this->validatePost();
        try {
            $updateKioskModel = new UpdatePos();
            $updateKioskModel->attributes = $this->request->post();
            $updateKioskModel->startUpdate = date('Y-m-d H:i:s');
            $updateKioskModel->pathFolder = "/esb-fnb-ods";
            if (!$updateKioskModel->downloadUpdateOds()) {
                throw new HttpException(
                    400,
                    Yii::t('app', 'Failed to download updates')
                );
            }

            if (!$updateKioskModel->applyUpdateOds()) {
                throw new HttpException(
                    400,
                    Yii::t('app', 'Failed to apply updates')
                );
            }
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionPosUpdateFile()
    {
        try {
            $updatePosModel = new UpdatePos();
            $updatePosModel->startUpdate = date('Y-m-d H:i:s');
            if (!$updatePosModel->downloadUpdate()) {
                throw new HttpException(
                    500,
                    Yii::t('app', 'Failed to download updates')
                );
            }
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    private function validatePost()
    {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
    }
}
