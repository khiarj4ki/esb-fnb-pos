<?php

use app\models\QuestionAnswer;
use yii\db\Migration;

/**
 * Class m200505_020336_create_tr_questionanswer
 */
class m200505_020336_create_tr_questionanswer extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(QuestionAnswer::tableName(), true) === null) {
            $this->createTable(QuestionAnswer::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'salesNum' => $this->string(20)->notNull(),
                    'questionID' => $this->integer()->notNull(),
                    'answerText' => $this->text()->notNull(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(QuestionAnswer::tableName(), true) !== null) {
            $this->dropTable(QuestionAnswer::tableName());
        }
    }
}
