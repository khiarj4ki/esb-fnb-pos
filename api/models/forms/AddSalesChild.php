<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\SalesHead;
use app\models\SalesPlatformFee;
use Yii;
use yii\base\Exception;
use yii\base\Model;

class AddSalesChild extends Model {
    public $salesNum;
    public $salesNumTarget;
    public $sourceSalesNum;
    public $salesModel;
    public $paxTotal;
    public $errMsg;
    public $additionalInfo;
    
    public function rules() {
        return [
            [['salesNum'], 'required'],
            [['salesNum'], 'string', 'max' => 20],
            [['additionalInfo'], 'string', 'max' => 200],
            [['salesNum'], 'validateSalesNum'],
            [['salesNum'], 'validatePaxTotal']
        ];
    }
    
    public function validateSalesNum($attribute) {
        $this->sourceSalesNum = $this->salesNum;
        $this->salesModel = SalesHead::find()
            ->andWhere(['salesNum' => $this->salesNum])
            ->one();
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid sales number');
        }
    }
    
    public function validatePaxTotal($attribute) {
        $salesHead = $this->salesModel;
        $this->paxTotal = $salesHead->paxTotal;
//        if ($this->paxTotal < 2) {
//            $this->addError($attribute, 'Pax total less than 2');
//        }
    }
    
    public function save() {
        if (!$this->validate()) {
            return false;
        }
        
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $salesHead = $this->salesModel;
            if ($salesHead->statusID == 8) {
                $this->errMsg = 'This order have been paid';
                throw new Exception('This order have been paid');
            }
            if (self::countSalesNumChild($this->salesNum) >= 35) {
                $this->errMsg = 'This order has reach maximum bill';
                throw new Exception('This order has reach max bill');
            }

            $newTransnum = AppHelper::createNewChildTransactionNumber($this->salesNum);
            $this->salesNumTarget = $newTransnum;
            $salesChildModel = new SalesHead();
            $salesChildModel->attributes = $salesHead->attributes;
            $salesChildModel->salesNum = $newTransnum;
            $salesChildModel->additionalInfo = $this->additionalInfo;
            $salesChildModel->billNum = NULL;
            $salesChildModel->paxTotal = 0;
            $salesChildModel->promotionID = 0;
            $salesChildModel->promotionDiscount = 0;
            if (!$salesChildModel->save()) {
                throw new Exception('Failed to save sales child');
            }
            $this->salesNum = $salesChildModel->salesNum;

            // Insert Platform Fee Data - Start
            $salesPlatformFees = SalesPlatformFee::find()
                ->where(['salesNum' => $salesHead->salesNum])
                ->asArray()
                ->all();

            if ($salesPlatformFees) {
                $salesPlatformFeeModel = new SalesPlatformFee();
                if (!$salesPlatformFeeModel->saveModel($this->salesNum, $salesPlatformFees)) {
                    throw new Exception(json_encode($salesPlatformFeeModel->errMsg), 500);
                }
            }
            // Insert Platform Fee Data - End
            
            Logging::save($this->sourceSalesNum, Logging::ADD_SALES_CHILD,
                $this->getAttributes());
            
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesNum', $ex->getMessage());
            return false;
        }
    }

    public static function countSalesNumChild($salesNum)
    {
        $paramFilter = $salesNum . '-';
        $model = SalesHead::find()
            ->where(["LIKE", "salesNum", $paramFilter])
            ->count();

        return $model;
    }
}