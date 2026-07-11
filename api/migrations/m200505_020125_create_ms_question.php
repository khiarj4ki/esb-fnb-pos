<?php

use app\models\Question;
use yii\db\Migration;

/**
 * Class m200505_020125_create_ms_question
 */
class m200505_020125_create_ms_question extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Question::tableName(), true) === null) {
            $this->createTable(Question::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'questionType' => $this->integer()->notNull(),
                    'questionText' => $this->text()->notNull(),
                    'acceptOtherAnswer' => $this->tinyInteger(1)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'order' => $this->integer()->defaultValue(NULL),
                    'createdBy' => $this->string(50)->defaultValue(NULL),
                    'createdDate' => $this->dateTime()->defaultValue(NULL),
                    'editedBy' => $this->string(50)->defaultValue(NULL),
                    'editedDate' => $this->dateTime()->defaultValue(NULL),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(Question::tableName(), true) !== null) {
            $this->dropTable(Question::tableName());
        }
    }
}
