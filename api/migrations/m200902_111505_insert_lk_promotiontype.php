<?php

use yii\db\Migration;
use app\models\PromotionType;

/**
 * Class m200902_111505_insert_lk_promotiontype
 */
class m200902_111505_insert_lk_promotiontype extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PromotionType::find()->where(['promotionTypeID' => 10])->exists()) {
            $this->insert(PromotionType::tableName(), [
                'promotionTypeID' => 10, 
                'promotionTypeDesc' => 'DISCOUNT LIMIT (%)']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if (PromotionType::find()->where(['promotionTypeID' => 10])->exists()) {
            $this->delete(PromotionType::tableName(), ['promotionTypeID' => 10]);
        }
    }
}
