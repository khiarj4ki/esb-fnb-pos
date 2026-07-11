<?php

use app\models\PosMode;
use yii\db\Migration;

/**
 * Class m200212_045133_insert_value_lk_posmode
 */
class m200212_045133_insert_value_lk_posmode extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!PosMode::find()->where(['posModeID' => '1', 'posModeName' => 'Full Services'])->exists()) {
            $this->insert(PosMode::tableName(),
                ['posModeID' => '1', 'posModeName' => 'Full Services']);
        }
        
        if (!PosMode::find()->where(['posModeID' => '2', 'posModeName' => 'Quick Services'])->exists()) {
            $this->insert(PosMode::tableName(),
                ['posModeID' => '2', 'posModeName' => 'Quick Services']);
        }
    }

    public function down()
    {
        $this->delete('lk_posmode', 'posModeName = "Full Services"');
        $this->delete('lk_posmode', 'posModeName = "Quick Services"');
    }
}
