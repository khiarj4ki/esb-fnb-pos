<?php

use app\models\Questionnaire;
use yii\db\Migration;

/**
 * Class m220920_040333_add_nextQuestionID_ms_questionnaire
 */
class m220920_040333_add_nextQuestionID_ms_questionnaire extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if($this->db->getTableSchema(Questionnaire::tableName(), true)->getColumn('nextQuestionID') === null) {
            $this->addColumn(
                Questionnaire::tableName(),
                'nextQuestionID',
                $this->integer(11)->defaultValue(null)->after('acceptOtherAnswer')
            );
        };
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Questionnaire::tableName(), true)->getColumn('nextQuestionID') !== null) {
            $this->dropColumn(Questionnaire::tableName(), 'nextQuestionID');
        }
    }
}
