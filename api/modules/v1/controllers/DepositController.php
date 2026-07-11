<?php
namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\models\forms\Deposit;
use app\models\forms\PrintMember;
use app\models\MemberDeposit;
use app\models\Setting;
use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\web\HttpException;

class DepositController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $branchID = Setting::getCurrentBranch();

        $memberDeposit = MemberDeposit::find()
            ->with('member')
            ->with('status');
        if ($this->request->post('startDate')) {
            $memberDeposit->andWhere(['>=', 'memberDepositDate', new Expression('DATE(\'' . $this->request->post('startDate') . '\')')]);
        }
        if ($this->request->post('endDate')) {
            $memberDeposit->andWhere(['<=', 'memberDepositDate', new Expression('DATE(\'' . $this->request->post('endDate') . '\')')]);
        }

        return $memberDeposit
                ->orderBy('memberDepositNum')
                ->all();
    }

    public function actionCreate() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $depositModel = new Deposit([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$depositModel->save()) {
                throw new Exception(json_encode($depositModel->errors));
            }
            
            return $depositModel->memberDepositNum;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionCreateOnline(){
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $depositModel = new Deposit([
            'attributes' => $this->request->post()
        ]);
        
        return $depositModel->saveOnline();
    }

    public function actionPrint() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintMember([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrintMember::SCENARIO_DEPOSIT;
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
        $rePrintingModel->scenario = PrintMember::SCENARIO_DEPOSIT;
        $rePrintingModel->rePrintMember = true;
        $rePrintingModel->doPrint();
        
        return AndroidPrintConnector::getData();
    }

}
