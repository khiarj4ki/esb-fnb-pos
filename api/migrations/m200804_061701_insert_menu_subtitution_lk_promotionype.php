<?php

use app\models\PromotionType;
use yii\db\Migration;

/**
 * Class m200804_061701_insert_menu_subtitution_lk_promotionype
 */
class m200804_061701_insert_menu_subtitution_lk_promotionype extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if (!PromotionType::find()->where(
                ['promotionTypeID' => '7', 'promotionTypeDesc' => 'MENU SUBSTITUTION']
            )->exists()) {
            $this->insert(PromotionType::tableName(),
                ['promotionTypeID' => '7', 'promotionTypeDesc' => 'MENU SUBSTITUTION']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $this->delete(PromotionType::tableName(),
            ['promotionTypeID' => '7', 'promotionTypeDesc' => 'MENU SUBSTITUTION']
        );
    }

}
