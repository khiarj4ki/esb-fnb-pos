<?php
namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\components\AppHelper;
use app\models\Day;
use app\models\forms\PrintEndShift;
use app\models\forms\PrintShiftOut;
use app\models\forms\PrintShiftOutCash;
use app\models\forms\Shift;
use app\models\forms\ShiftReport;
use app\models\PosUserAccess;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\forms\Logging;
use app\models\SalesHead;
use app\models\ShiftLogCash;
use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\web\HttpException;

class ShiftController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'last-shift'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $branchID = Setting::getCurrentBranch();

        $shiftLog = ShiftLog::find()
            ->with('shiftInUser')
            ->with('shiftOutUser')
            ->andWhere(['branchID' => $branchID]);
        if ($this->request->post('startDate')) {
            $shiftLog->andWhere(['>=', 'DATE(shiftInTime)', new Expression('DATE(\'' . $this->request->post('startDate') . '\')')]);
        }
        if ($this->request->post('endDate')) {
            $shiftLog->andWhere(['<=', 'DATE(shiftInTime)', new Expression('DATE(\'' . $this->request->post('endDate') . '\')')]);
        }

        return $shiftLog
                ->orderBy('shiftInTime DESC, shiftID DESC')
                ->all();
    }

    public function actionCurrent() {
        if (!ShiftLog::findActive()) {
            return null;
        }

        $shiftReportModel = new ShiftReport();
        $shiftReportModel->scenario = ShiftReport::SCENARIO_BY_ID;
        return [
            'salesRecap' => $shiftReportModel->getSalesRecap(),
            'salesPaymentRecap' => $shiftReportModel->getSalesPaymentRecap(),
            'shiftLog' => $shiftReportModel->getShiftHead(),
            'shiftLogDetail' => $shiftReportModel->getShiftDetail(),
            'shiftLogCash' => $shiftReportModel->getShiftCash(),
            'salesMenuRecap' => $shiftReportModel->getSalesMenu(),
            'salesByMenuQtyValue' => $shiftReportModel->getSalesByMenuQtyValue(),
            'salesByMenuQty' => $shiftReportModel->getSalesByMenuQty(),
            'nonSalesByMenu' => $shiftReportModel->getNonSalesByMenu(),
            'customMenuSales' => $shiftReportModel->getCustomMenuSales(),
            'overVoucherValue' => $shiftReportModel->getOverVoucherValue(),
            'salesByTableSection' => $shiftReportModel->getSalesByTableSection(),
            'stockBranchMenu' => $shiftReportModel->getBranchMenu()
        ];
    }

    public function actionView() {
        if (!$this->request->post('shiftID')) {
            throw new HttpException(400);
        }

        $shiftReportModel = new ShiftReport([
            'attributes' => $this->request->post()
        ]);
        $shiftReportModel->scenario = ShiftReport::SCENARIO_BY_ID;
        return [
            'salesRecap' => $shiftReportModel->getSalesRecap(),
            'salesPaymentRecap' => $shiftReportModel->getSalesPaymentRecap(),
            'salesPaymentPerCashier' => $shiftReportModel->getSalesPaymentPerCashier(),
            'shiftLog' => $shiftReportModel->getShiftHead(),
            'shiftLogDetail' => $shiftReportModel->getShiftDetail(),
            'shiftLogCash' => $shiftReportModel->getShiftCash(),
            'pendingSales' => $shiftReportModel->getPendingSales(),
            'salesMenuPerCategory' => $shiftReportModel->getSalesMenuPerCategory(),
            'salesMenuPerCategoryDetail' => $shiftReportModel->getSalesMenuPerCategoryDetail(),
            'salesMenu' => $shiftReportModel->getSalesMenu(),
            'cancelledMenu' => $shiftReportModel->getCancelledMenu(),
            'cancelledMenuPerSales' => $shiftReportModel->getCancelledMenuPerSales(),
            'promotionSummary' => $shiftReportModel->getPromotionSummary(),
            'nonSalesPaymentRecap' => $shiftReportModel->getNonSalesPaymentRecap(),
            'nonSalesPaymentPerCashier' => $shiftReportModel->getNonSalesPaymentPerCashier(),
            'salesMenuPackage' => $shiftReportModel->getSalesMenuPackage(),
            'nonSalesBillSummary' => $shiftReportModel->getNonSalesBillSummary(),
            'nonSalesMenuSummary' => $shiftReportModel->getNonSalesMenuSummary(),            
            'salesVoucherUsage' => $shiftReportModel->getSalesVoucherUsage(),
            'salesByVisitPurpose' => $shiftReportModel->getSalesByVisitPurpose(),
            'salesByMenuQtyValue' => $shiftReportModel->getSalesByMenuQtyValue(),
            'salesByMenuQty' => $shiftReportModel->getSalesByMenuQty(),
            'nonSalesByMenu' => $shiftReportModel->getNonSalesByMenu(),
            'customMenuSales' => $shiftReportModel->getCustomMenuSales(),
            'overVoucherValue' => $shiftReportModel->getOverVoucherValue(),
            'salesByTableSection' => $shiftReportModel->getSalesByTableSection()
        ];
    }

    public function actionIn() {
        $this->validatePost();

        $shiftModel = new Shift([
            'attributes' => $this->request->post()
        ]);
        
        $date = date('Y-m-d');
        $branchID = Setting::getCurrentBranch();
        $posUserRoleID = Yii::$app->user->identity->posUserRoleID;

        $shiftLogModel = ShiftLog::find()
            ->where(['like', 'shiftInTime', $date])
            ->andWhere(['IS NOT', 'shiftOutTime', null])
            ->andWhere(['branchID' => $branchID])
            ->all();

        if(count($shiftLogModel) > 0){
            $reStartShiftAccess = PosUserAccess::findOne(['posUserRoleID' => $posUserRoleID, 'filterAccessID' => 'F6'])['hasAccess'];
        }else{
            $reStartShiftAccess = 1;
        }
        
        if ($reStartShiftAccess != 1) {
            throw new HttpException(500, Yii::t('app', 'You do not have access to start shift more than 1 times in a day'));
        }
        
        $shiftModel->scenario = Shift::SCENARIO_SHIFT_IN;
        if (!$shiftModel->save()) {
            throw new HttpException(500, Yii::t('app', 'Failed to shift in'));
        }
    }

    public function actionValidateIn() {
        $this->validatePost();

        $shiftModel = new Shift([
            'attributes' => $this->request->post()
        ]);
        
        $shiftModel->scenario = Shift::SCENARIO_SHIFT_IN;
        if (!$shiftModel->save()) {
            throw new HttpException(500, Yii::t('app', 'Failed to shift in'));
        }
    }

    public function actionOut() {
        $this->validatePost();

        $shiftModel = new Shift([
            'attributes' => $this->request->post()
        ]);
        try {
            $shiftModel->scenario = Shift::SCENARIO_SHIFT_OUT;
            if (!$shiftModel->save()) {
                throw new Exception(json_encode($shiftModel->errors));
            }

            return $shiftModel->shiftID;
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to shift out'));
        }
    }

    public function actionEnd() {
        $shiftModel = new Shift();
        $shiftModel->scenario = Shift::SCENARIO_END_SHIFT;
        if (!$shiftModel->save()) {
            throw new HttpException(500, Yii::t('app', 'Failed to end shift'));
        }

        return $shiftModel->shiftDetailID;
    }

    public function actionPrintEnd() {
        $model = new PrintEndShift([
            'attributes' => $this->request->post()
        ]);
        $model->doPrint();
        
        if ($model->printResult) {
            return [
                "printDataError" => $model->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintOut() {
        $model = NULL;
        //@Notes: Check shift mode
        $shiftModel = ShiftLogCash::findByShiftId($this->request->post('shiftID'));
        if ($shiftModel) {
            $model = new PrintShiftOutCash([
                'attributes' => $this->request->post()
            ]);
        } else {
            $model = new PrintShiftOut([
                'attributes' => $this->request->post()
            ]);
        }
        
        $model->doPrint();
        
        if ($model->printResult) {
            return [
                "printDataError" => $model->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionLimitEndDay() {
        return Day::findModel()->asArray()->all();
    }

    private function validatePost() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
    }

    public function actionLastShift(){
        $query = (new Query())
            ->select([
                'lastShiftIn' => new Expression("max(shiftInTime)"),
                'lastShiftOut' => new Expression("max(shiftOutTime)")
            ])
            ->from(ShiftLog::tableName())
        ->one();
        
        return [
            "lastShiftIn" => $query['lastShiftIn'],
            "lastShiftOut" => $query['lastShiftOut'],
        ];
    }

    public function actionValidateServerClock() {
        return Shift::getServerClock();
    }

    public function actionHandlingOrder(){
        try{
            $tableSalesHead = SalesHead::tableName();
            $tableShiftLog = ShiftLog::tableName();
            $salesData = Yii::$app->db->createCommand("
                SELECT DISTINCT 
                    salesNum,
                    salesDateOut,
                    salesDateIn,
                    salesDate
                FROM 
                    $tableSalesHead
                WHERE 
                    salesDateOut > (
                        SELECT MAX(shiftOutTime) 
                        FROM $tableShiftLog
                    )
                AND
                    (
                        DATE(salesDate) = (
                            SELECT DATE(MAX(ShiftInTime)) 
                            from $tableShiftLog
                        )
                    )
            ")->queryAll();

            if(!empty($salesData)){
                foreach ($salesData as $sales) {
                    $beforeSalesDateOut = $sales['salesDateOut'];
                    $currentTime = date('Y-m-d H:i:s');
                    Yii::$app->db->createCommand()
                    ->update('tr_saleshead', 
                        [
                            'salesDateOut' => $sales['salesDateIn'],
                            'syncDate' => null
                        ], 
                        "salesNum = :salesNum", 
                        [':salesNum' => $sales['salesNum']])
                    ->execute();   
                    $logData = ["salesNum" => $sales['salesNum'], 'prevSalesDateOut' => $beforeSalesDateOut, 'newSalesDateOut' => $sales['salesDateIn'], 'updateTime' => $currentTime];
                    Logging::save($sales['salesNum'], Logging::UPDATE_SALES_DATE_OUT, $logData);
                }
            }
            return true;
        }catch(Exception $e){
            Yii::error($e);
            return false;
        }
    }
}
