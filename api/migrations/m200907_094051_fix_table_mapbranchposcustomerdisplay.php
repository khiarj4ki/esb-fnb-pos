<?php

use app\components\AppHelper;
use app\models\MapBranchPosCustomerDisplay;
use yii\db\Migration;

/**
 * Class m200907_094051_fix_table_mapbranchposcustomerdisplay
 */
class m200907_094051_fix_table_mapbranchposcustomerdisplay extends Migration {

    /**
     * @inheritdoc
     */
    public function up() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        if ($this->db->getTableSchema($mainDbName . '.map_branchcposcustomerdisplay',
                        true) !== null) {
            $this->dropTable($mainDbName . '.map_branchcposcustomerdisplay');
        }

        if ($this->db->getTableSchema($mainDbName . '.map_branchposcustomerdisplay',
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
        
    }

}
