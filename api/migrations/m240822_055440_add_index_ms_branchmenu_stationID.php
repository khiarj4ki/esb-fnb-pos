<?php

use app\models\BranchMenu;
use yii\db\Migration;

/**
 * Class m240822_055440_add_index_ms_branchmenu_stationID
 */
class m240822_055440_add_index_ms_branchmenu_stationID extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $chekQuery = "SHOW INDEX FROM " . BranchMenu::tableName() . " WHERE Key_name ='idx_branchmenu_stationID'";
        if (!$this->db->createCommand($chekQuery)->queryScalar()) {
            $this->createIndex('idx_branchmenu_stationID', BranchMenu::tableName(), 'stationID');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkIndex = "SHOW INDEX FROM " . BranchMenu::tableName() . " WHERE Key_name = 'idx_branchmenu_stationID'";
        if ($this->db->createCommand($checkIndex)->queryScalar()) {
            $this->dropIndex('idx_branchmenu_stationID', BranchMenu::tableName());
        }
    }
}
