<?php

use app\models\ShiftLogMode;
use yii\db\Migration;

/**
 * Class m241216_084308_create_tr_shiftlogmode
 */
class m241216_084308_create_tr_shiftlogmode extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(ShiftLogMode::tableName(), true) === null) {
            $this->createTable(ShiftLogMode::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'shiftID' => $this->integer()->notNull(),
                    'shiftMode' => $this->string(20)->notNull(),
                    'syncDate' => $this->datetime(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(ShiftLogMode::tableName(), true) !== null) {
            $this->dropTable(ShiftLogMode::tableName());
        }
    }
}
