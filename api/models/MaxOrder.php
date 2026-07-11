<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "ms_maxorder".
 *
 * @property int $maxOrderID
 * @property int $visitPurposeID
 * @property int $maxOrderID
 * @property string $maxOrder
 * @property int $notes
 *
 */

class MaxOrder extends ActiveRecord {
    public static function tableName(){
        return 'ms_maxorder';
    }

    public function rules(){
        return [
            [['maxOrderID', 'visitPurposeID', 'maxOrder'], 'required'],
            [['maxOrderID', 'visitPurposeID'], 'integer'],
            [['visitPurposeID', 'maxOrder', 'notes'], 'safe'],
        ];
    }
    
    public static function getKioskMaxOrder($visitPurposeID){
        $query = (new Query)
        ->select([
            'maxOrder.maxOrderID',
            'maxOrder.visitPurposeID',
            'maxOrder.maxOrder',
            'menuCategoryDetailIDs' => new Expression("GROUP_CONCAT(maxOrderDetail.menuCategoryDetailID separator ',')")
        ])
        ->from(MaxOrder::tableName() . ' maxOrder')
        ->innerJoin(MaxOrderDetail::tableName() . ' maxOrderDetail', "maxOrderDetail.maxOrderID = maxOrder.maxOrderID")
        ->andWhere(["maxOrder.visitPurposeID" => $visitPurposeID])
        ->groupBy(['maxOrderID', 'visitPurposeID', 'maxOrder'])
        ->orderBy([
            'maxOrderID' => SORT_ASC,
            'visitPurposeID' => SORT_ASC,
            'maxOrder' => SORT_ASC
        ])
        ->all();

        return $query;
    }
}