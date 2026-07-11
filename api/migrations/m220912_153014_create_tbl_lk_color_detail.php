<?php

use app\models\LkColorDetail;
use yii\db\Migration;

/**
 * Class m220912_153014_create_tbl_lk_color_detail
 */
class m220912_153014_create_tbl_lk_color_detail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(LkColorDetail::tableName(), true) === null) {
            $this->createTable(LkColorDetail::tableName(), [
                'ID' => $this->primaryKey(),
                'colorID' => $this->integer()->notNull(),
                'kioskMode' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull(),
                'btnCategoryColorCode' => $this->string(7)->notNull(),
                'btnCancelColorCode' => $this->string(7)->notNull(),
                'btnSearchColorCode' => $this->string(7)->notNull(),
                'btnBackColorCode' => $this->string(7)->notNull(),
                'indicatorDiscColorCode' => $this->string(7)->notNull()
            ]);
        }    
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(LkColorDetail::tableName(), true) !== null) {
            $this->dropTable(LkColorDetail::tableName());
        }
    }
}
