<?php

use app\models\BranchMenu;
use yii\db\Migration;

/**
 * Class m230215_042945_add_index_ms_branchmenu_branchID
 */
class m230215_042945_add_index_ms_branchmenu_branchID extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $chekQuery = "SHOW INDEX FROM " . BranchMenu::tableName() . " WHERE Key_name ='idx_branchmenu_branchID'";
        if (!$this->db->createCommand($chekQuery)->queryScalar()) {
            $this->createIndex('idx_branchmenu_branchID', BranchMenu::tableName(), 'branchID');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkIndex = "SHOW INDEX FROM " . BranchMenu::tableName() . " WHERE Key_name = 'idx_branchmenu_branchID'";
        if ($this->db->createCommand($checkIndex)->queryScalar()) {
            $this->dropIndex('idx_branchmenu_branchID', BranchMenu::tableName());
        }
    }
}
