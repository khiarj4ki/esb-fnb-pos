<?php

namespace app\models;
use Yii;
use yii\db\ActiveRecord;

class LkColorDetail extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'lk_colordetail';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['colorID', 'kioskMode', 'btnCategoryColorCode', 'btnCancelColorCode', 'btnSearchColorCode', 'btnBackColorCode', 'indicatorDiscColorCode'], 'required'],
            [['colorID', 'kioskMode'], 'integer'],
            [['btnCategoryColorCode', 'btnCancelColorCode', 'btnSearchColorCode', 'btnBackColorCode', 'indicatorDiscColorCode'], 'string']
        ];
    }
    
}
