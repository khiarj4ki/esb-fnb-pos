<?php

use app\models\ShiftLogCash;
use yii\db\Migration;

/**
 * Class m241209_034441_create_tr_shiftlogcash
 */
class m241209_034441_create_tr_shiftlogcash extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(ShiftLogCash::tableName(), true) === null) {
            $this->createTable(ShiftLogCash::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'shiftID' => $this->integer()->notNull(),
                    'shiftNumber' => $this->integer()->notNull(),
                    'shiftInTime' => $this->datetime()->notNull(),
                    'shiftOutTime' => $this->datetime(),
                    'startingCash' => $this->decimal(20,4)->notNull(),
                    'systemCashReceivedTotal' => $this->decimal(20,4),
                    'endingCash' => $this->decimal(20,4),
                    'shiftInUsername' => $this->string(50)->notNull(),
                    'shiftOutUsername' => $this->string(50),
                    'closingNotes' => $this->text(),
                    'syncDate' => $this->datetime(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(ShiftLogCash::tableName(), true) !== null) {
            $this->dropTable(ShiftLogCash::tableName());
        }
    }
}
