<?php
namespace app\modules\v1\controllers;

use app\components\AppHelper;
use app\models\forms\UpdateRemarks;
use app\models\forms\VoidMenuSales;
use app\models\forms\VoidSales;
use app\models\SalesHead;
use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\web\HttpException;

class SalesController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $salesModel = SalesHead::findFinished()
            ->joinWith('table')
            ->joinWith('member')
            ->joinWith('customer')
            ->with('creator')
            ->with('editor')
            ->joinWith('status')
            ->joinWith('salesPayments.paymentMethod')
            ->joinWith('visitPurpose');
        
        $dateRange = $this->request->post('dateRange');
        if (isset($dateRange)) {
            $startDate = date('Y-m-d', strtotime($dateRange['startDate']));
            $endDate = date('Y-m-d', strtotime($dateRange['endDate']));
            $salesModel->andWhere(['between', 'salesDate', $startDate, $endDate])
                ->orderBy(['salesDateOut' => SORT_DESC]);
        }
        
        $salesList = [];
        foreach ($salesModel->all() as $sales) {
            $salesArr['salesNum'] = $sales->salesNum;
            $salesArr['billNum'] = $sales->billNum;
            $salesArr['salesDate'] = $sales->salesDate;
            $salesArr['memberName'] = $sales->member ? $sales->member->memberName : 'Non Member';
            $salesArr['externalMemberName'] = ($sales->externalMemberName || $sales->externalMemberName !== 'null') ? $sales->externalMemberName : 'Non Member';
            $salesArr['customerName'] = $sales->customer ? $sales->customer->fullName : '-';
            $salesArr['tableName'] = $sales->table ? $sales->table->tableName : 'Quick Service';
            $salesArr['grandTotal'] = $sales->grandTotal;
            $salesArr['statusName'] = $sales->status->statusName;
            $salesArr['salesDateIn'] = str_replace("-", "/", $sales->salesDateIn);
            $salesArr['creator'] = SalesHead::getCreatorEditor($sales->createdBy, $sales->creator);
            $salesArr['roundingTotal'] = $sales->roundingTotal;
            $salesArr['editor'] = SalesHead::getCreatorEditor($sales->editedBy, $sales->editor);
            $salesArr['salesDateOut'] =  str_replace("-", "/", $sales->salesDateOut);
            $salesArr['visitPurposeName'] = (isset($sales->visitPurpose) && $sales->visitPurpose->visitPurposeName) ? $sales->visitPurpose->visitPurposeName : '-';
            $salesArr['additionalInfo'] = $sales->additionalInfo;
            
            $paymentMethods = '';
            $selfOrderIDs = '';
            foreach ($sales->salesPayments as $salesPayment) {
                $paymentMethods .= $salesPayment->paymentMethod->paymentMethodName . ', ';
            }
            if (strlen($paymentMethods) > 0) {
                $paymentMethods = substr($paymentMethods, 0, strlen($paymentMethods) - 2);
            }

            foreach ($sales->salesPayments as $salesPayment) {
                $selfOrderIDs .= $salesPayment->selfOrderID . ', ';
            }
            if (strlen($selfOrderIDs) > 0) {
                $selfOrderIDs = substr($selfOrderIDs, 0, strlen($selfOrderIDs) - 2);
            }

            $salesArr['paymentMethods'] = $paymentMethods;
            $salesArr['selfOrderIDs'] = $selfOrderIDs;
            $salesList[] = $salesArr;
        }
        return $salesList;
    }

    public function actionView() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }

        $sales = SalesHead::findSalesAsArray($this->request->post('salesNum'));
        if (!$sales) {
            throw new HttpException(404, 'Order not found');
        }
        return $sales;
    }
    
    public function actionMenuVoid() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $voidModel = new VoidMenuSales([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$voidModel->save()) {
                throw new Exception(json_encode($voidModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data ' . $ex->getMessage()));
        }
    }

    public function actionVoid() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $voidModel = new VoidSales([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$voidModel->save()) {
                throw new Exception(json_encode($voidModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(400, $ex->getMessage());
        }
    }
    
    public function actionUpdateRemarks() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $updateRemarksModel = new UpdateRemarks([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$updateRemarksModel->save()) {
                throw new Exception(json_encode($updateRemarksModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data '. $ex->getMessage()));
        }
    }

}
