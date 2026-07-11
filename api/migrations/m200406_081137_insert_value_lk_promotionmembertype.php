<?php

use app\models\PromotionMemberType;
use yii\db\Migration;

/**
 * Class m200406_081137_insert_value_lk_promotionmembertype
 */
class m200406_081137_insert_value_lk_promotionmembertype extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!PromotionMemberType::find()->where(['promotionMemberTypeID' => '0', 'promotionMemberTypeName' => 'All Transaction'])->exists()) {
            $this->insert(PromotionMemberType::tableName(),
                ['promotionMemberTypeID' => '0', 'promotionMemberTypeName' => 'All Transaction']);
        }
        
        if (!PromotionMemberType::find()->where(['promotionMemberTypeID' => '1', 'promotionMemberTypeName' => 'Member & Staff'])->exists()) {
            $this->insert(PromotionMemberType::tableName(),
                ['promotionMemberTypeID' => '1', 'promotionMemberTypeName' => 'Member & Staff']);
        }
        
        if (!PromotionMemberType::find()->where(['promotionMemberTypeID' => '2', 'promotionMemberTypeName' => 'Staff Only'])->exists()) {
            $this->insert(PromotionMemberType::tableName(),
                ['promotionMemberTypeID' => '2', 'promotionMemberTypeName' => 'Staff Only']);
        }
        
        if (!PromotionMemberType::find()->where(['promotionMemberTypeID' => '3', 'promotionMemberTypeName' => 'Member Only'])->exists()) {
            $this->insert(PromotionMemberType::tableName(),
                ['promotionMemberTypeID' => '3', 'promotionMemberTypeName' => 'Member Only']);
        }
    }

    public function down()
    {
        $this->delete('lk_promotionmembertype', 'promotionMemberTypeName = "All Transaction"');
        $this->delete('lk_promotionmembertype', 'promotionMemberTypeName = "Member & Staff"');
        $this->delete('lk_promotionmembertype', 'promotionMemberTypeName = "Staff Only"');
        $this->delete('lk_promotionmembertype', 'promotionMemberTypeName = "Member Only"');
    }
}
