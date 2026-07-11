<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_promotionrules".
 *
 * @property int $promotionRulesID
 * @property string $promotionRulesName
 */
class PromotionRules extends ActiveRecord {
    public static function tableName() {
        return 'lk_promotionrules';
    }

    public function rules() {
        return [
            [['promotionRulesName'], 'string', 'max' => 50]
        ];
    }

    public function attributeLabels() {
        return [
            'promotionRulesID' => 'Promotion Rules ID',
            'promotionRulesName' => 'Promotion Rules Name',
        ];
    }
}
