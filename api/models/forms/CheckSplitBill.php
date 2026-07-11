<?php
namespace app\models\forms;

use Yii;
use yii\base\Model;
use app\models\SalesHead;

/**
 * @property boolean $salesNum
 */
class CheckSplitBill extends Model {
    public $salesNum;
    public $tableID;
    public $isSplitBill;
    public $newSalesNum;
    
    public function rules() {
        return [
            [['salesNum', 'tableID'], 'required'],
            [['salesNum'], 'string', 'max' => 20],
            [['salesNum'], 'validateSalesNum']
        ];
    }
    
    public function validateSalesNum($attribute) {
        $salesSplitBill = SalesHead::find()
            ->where(['LIKE', 'salesNum', $this->salesNum])
            ->andWhere(['tableID' => $this->tableID])
            ->all();
        
        if ($salesSplitBill) {
            if (count($salesSplitBill) > 1) {
                $this->isSplitBill = true;
                foreach ($salesSplitBill as $splitSales) {
                    if (strpos($splitSales->salesNum, '-') === false) {
                        $this->newSalesNum = $splitSales->salesNum;
                    }
                }
            } else {
                $this->isSplitBill = false;
                foreach ($salesSplitBill as $splitSales) {
                    $this->newSalesNum = $splitSales->salesNum;
                }
            }
        } else {
            $this->newSalesNum = $this->salesNum;
        }
    }
    
    public function hasSplitBill() {
        try {
            $this->validate();
            
            return [
                'tableStatus' => $this->isSplitBill,
                'salesNum' => $this->newSalesNum
            ];
        } catch (\Exception $ex) {
            $this->addError('salesNum', $ex->getMessage());
            return false;
        }
    }
}