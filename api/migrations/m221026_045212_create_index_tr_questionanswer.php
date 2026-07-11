<?php

use app\models\QuestionAnswer;
use yii\db\Migration;

/**
 * Class m221026_045212_create_index_tr_questionanswer
 */
class m221026_045212_create_index_tr_questionanswer extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $checkIndex = "SHOW INDEX FROM " . QuestionAnswer::tableName() . " WHERE Key_name = 'idx_questionanswer_salesNum'";
        if (!$this->db->createCommand($checkIndex)->queryScalar()) {
            $this->createIndex('idx_questionanswer_salesNum', QuestionAnswer::tableName(), 'salesNum');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkIndex = "SHOW INDEX FROM " . QuestionAnswer::tableName() . " WHERE Key_name = 'idx_questionanswer_salesNum'";
        if ($this->db->createCommand($checkIndex)->queryScalar()) {
            $this->dropIndex('idx_questionanswer_salesNum', QuestionAnswer::tableName());
        }
    }

}
