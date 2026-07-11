<?php

use app\models\PromotionPackageSub;
use yii\db\Migration;

/**
 * Class m221227_031851_create_table_ms_promotionpackagesub
 */
class m221227_031851_create_table_ms_promotionpackagesub extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionPackageSub::tableName(), true) === null) {
            $this->createTable(PromotionPackageSub::tableName(),
                [
                    'ID' => $this->primaryKey(11),
                    'promotionID' => $this->integer(11)->notNull(),
                    'menuID' => $this->integer(11)->notNull(),
                    'menuSubsID' => $this->integer(11)
                ]
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PromotionPackageSub::tableName(), true) !== null) {
            $this->dropTable(PromotionPackageSub::tableName());
        }
    }
}
