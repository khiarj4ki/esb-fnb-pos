<?php

use yii\db\Migration;
use app\models\MapBranchPosCustomerDisplay;

/**
 * Class m200904_065102_create_map_branchposcustomerdisplay
 */
class m200904_065102_create_map_branchposcustomerdisplay extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MapBranchPosCustomerDisplay::tableName(),
                true) === null) {
            $this->createTable(MapBranchPosCustomerDisplay::tableName(),
                [
                    'posCustomerDisplayID' => $this->integer(11)->notNull(),
                    'branchID' => $this->integer(11)->notNull()
                ]);

            $this->addPrimaryKey('PRIMARYKEY',
                MapBranchPosCustomerDisplay::tableName(),
                ['posCustomerDisplayID', 'branchID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapBranchPosCustomerDisplay::tableName(),
                true) !== null) {
            $this->dropTable(MapBranchPosCustomerDisplay::tableName());
        }
    }
}
