<?php

use app\models\QuestionAnswer;
use yii\db\Migration;

/**
 * Class m211008_112539_add_syncdate_tr_questionanswer
 */
class m211008_112539_add_syncdate_tr_questionanswer extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(QuestionAnswer::tableName(), true)->getColumn('syncDate') === null) {
            $this->addColumn(QuestionAnswer::tableName(),
                'syncDate',
                $this->dateTime()->after('answerText')->null());
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(QuestionAnswer::tableName(), true)->getColumn('syncDate') !== null) {
            $this->dropColumn(QuestionAnswer::tableName(),
                'syncDate');
        }
    }
}
