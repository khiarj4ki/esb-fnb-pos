<?php

use app\models\QuestionOption;
use yii\db\Migration;

/**
 * Class m200505_020133_create_ms_questionoption
 */
class m200505_020133_create_ms_questionoption extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(QuestionOption::tableName(), true) === null) {
            $this->createTable(QuestionOption::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'questionID' => $this->integer()->notNull(),
                    'answerText' => $this->string(100)->notNull(),
                    'nextQuestionID' => $this->integer()->defaultValue(NULL),
                    'order' => $this->integer()->notNull(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(QuestionOption::tableName(), true) !== null) {
            $this->dropTable(QuestionOption::tableName());
        }
    }
}
