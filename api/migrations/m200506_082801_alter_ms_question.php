<?php

use app\models\Questionnaire;
use yii\db\Migration;

/**
 * Class m200506_082801_alter_ms_question
 */
class m200506_082801_alter_ms_question extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema('ms_question', true)) {
            $this->renameTable('ms_question', Questionnaire::tableName());
        }
    }
    
    public function down() {
    }
}
