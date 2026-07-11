<?php

use app\models\EsoLogEvent;
use yii\db\Migration;

/**
 * Class m250130_091249_alter_tr_eso_log_proses
 */
class m250130_091249_alter_tr_eso_log_proses extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
      
        if ($this->db->getTableSchema(EsoLogEvent::tableName(), true) === null) {
            $this->createTable(EsoLogEvent::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'branchID' => $this->integer()->notNull(),
                    'eventDate' => $this->dateTime()->notNull(),
                    'refNum' => $this->string(20)->notNull(),
                    'eventSubject' => $this->string(50)->notNull(),
                    'eventDescription' => $this->text()->notNull(),
                    'eventType' => $this->string(50)->notNull(),
                    'isSuccess' => $this->tinyInteger(1)->defaultValue(0)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(EsoLogEvent::tableName(), true) !== null) {
            $this->dropTable(EsoLogEvent::tableName());
        }
    }
}
