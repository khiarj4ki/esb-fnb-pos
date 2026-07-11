<?php

use yii\db\Migration;
use app\models\PromotionType;
/**
 * Class m200715_032445_insert_lk_promotiontype_menu_discount_rp
 */
class m200715_032445_insert_lk_promotiontype_menu_discount_rp extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PromotionType::find()->where(['promotionTypeID' => 9])->exists()) {
            $this->insert(PromotionType::tableName(), [
                'promotionTypeID' => 9, 
                'promotionTypeDesc' => 'MENU DISCOUNT (RP)']);

            $this->update(PromotionType::tableName(), [
                'promotionTypeDesc' => 'BILL DISCOUNT (RP)'
            ], [
                'promotionTypeID' => 3]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if (PromotionType::find()->where(['promotionTypeID' => 9])->exists()) {
            $this->delete(PromotionType::tableName(), ['promotionTypeID' => 9]);
            $this->update(PromotionType::tableName(), [
                'promotionTypeDesc' => 'DISCOUNT (RP)'
            ], [
                'promotionTypeID' => 3]);
        }
    }
}
