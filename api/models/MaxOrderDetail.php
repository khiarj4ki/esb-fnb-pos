<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_maxorderdetail".
 *
 * @property int $maxOrderDetailID
 * @property int $menuCategoryDetailID
 *
 */

class MaxOrderDetail extends ActiveRecord {
    public static function tableName(){
        return 'ms_maxorderdetail';
    }

    public function rules(){
        return [
            [['maxOrderDetailID', 'maxOrderID', 'menuCategoryDetailID'], 'required'],
            [['maxOrderDetailID', 'maxOrderID', 'menuCategoryDetailID'], 'integer'],
        ];
    }
}