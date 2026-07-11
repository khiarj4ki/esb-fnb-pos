<?php

use app\models\PromotionPrefix;
use yii\db\mysql\Schema;
use yii\db\Migration;

/**
 * Class m221114_090346_create_table_ms_promotionprefix
 */
class m221114_090346_create_table_ms_promotionprefix extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(PromotionPrefix::tableName(), true) === null) {
            $this->createTable(PromotionPrefix::tableName(), [
                'ID' => $this->primaryKey(11),
                'promotionID' => $this->integer(11)->notNull(),
                'prefix' => $this->string(20)->notNull(),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PromotionPrefix::tableName(), true) !== null) {
            $this->dropTable(PromotionPrefix::tableName());
        }
    }
}
