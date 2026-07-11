<?php

use app\models\PromotionMemberType;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m200406_080225_create_lk_promotionmembertype
 */
class m200406_080225_create_lk_promotionmembertype extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionMemberType::tableName(), true) === null) {
            $this->createTable(PromotionMemberType::tableName(),
                [
                'promotionMemberTypeID' => $this->integer()->notNull()->append('PRIMARY KEY'),
                'promotionMemberTypeName' => $this->string(50)->notNull()
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionMemberType::tableName(), true) !== null) {
            $this->dropTable(PromotionMemberType::tableName());
        }
    }
}
