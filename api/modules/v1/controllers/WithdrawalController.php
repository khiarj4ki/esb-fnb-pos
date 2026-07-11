<?php
namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\models\DepositWithdrawalHead;
use app\models\forms\PrintMember;
use app\models\forms\Withdrawal;
use app\models\Setting;
use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\web\HttpException;

class WithdrawalController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $branchID = Setting::getCurrentBranch();

        $depositWithdrawal = DepositWithdrawalHead::find()
            ->with('member')
            ->with('status');
        if ($this->request->post('startDate')) {
            $depositWithdrawal->andWhere(['>=', 'depositWithdrawalDate', new Expression('DATE(\'' . $this->request->post('startDate') . '\')')]);
        }
        if ($this->request->post('endDate')) {
            $depositWithdrawal->andWhere(['<=', 'depositWithdrawalDate', new Expression('DATE(\'' . $this->request->post('endDate') . '\')')]);
        }

        return $depositWithdrawal
                ->orderBy('depositWithdrawalNum')
                ->all();
    }

    public function actionCreate() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $withdrawalModel = new Withdrawal([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$withdrawalModel->save()) {
                throw new Exception(json_encode($withdrawalModel->errors));
            }
            
            return $withdrawalModel->depositWithdrawalNum;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionCreateOnline(){
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $withdrawalModel = new Withdrawal([
            'attributes' => $this->request->post()
        ]);

        return $withdrawalModel->saveOnline();
    }

    public function actionPrint() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintMember([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrintMember::SCENARIO_WITHDRAWAL;
        $printingModel->doPrint();
        
        return AndroidPrintConnector::getData();
    }

    public function actionRePrintDeposit() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $rePrintingModel = new PrintMember([
            'attributes' => $this->request->post()
        ]);
        $rePrintingModel->scenario = PrintMember::SCENARIO_WITHDRAWAL;
        $rePrintingModel->rePrintMember = true;
        $rePrintingModel->doPrint();
        
        return AndroidPrintConnector::getData();
    }

}
