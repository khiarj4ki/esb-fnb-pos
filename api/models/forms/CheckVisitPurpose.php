<?php
namespace app\models\forms;

use app\models\SalesHead;
use app\models\Setting;
use Exception;
use yii\base\Model;

class CheckVisitPurpose extends Model {
    
    private static function getVisitPurposebySalesNum($salesNum){
        $branchID = Setting::getCurrentBranch();

        return SalesHead::find()
                ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                ->andWhere([SalesHead::tableName() . '.salesNum' => $salesNum])->one();
    }

    public static function validateOrder($salesNum = null, $visitPurposeID = 0){
        if($salesNum && (self::getVisitPurposebySalesNum($salesNum)->visitPurposeID != $visitPurposeID)){
            throw new Exception('Visit purpose does not match.', 400);
        }
    }
   
}