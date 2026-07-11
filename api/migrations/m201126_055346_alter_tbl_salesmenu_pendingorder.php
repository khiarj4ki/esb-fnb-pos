<?php

use yii\db\Migration;
use app\models\SalesMenu;

/**
 * Class m201126_055346_alter_tbl_salesmenu_pendingorder
 */
class m201126_055346_alter_tbl_salesmenu_pendingorder extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('flagPending') === null) {
            $this->addColumn(SalesMenu::tableName(),
                'flagPending',
                $this->integer()->defaultValue(NULL)->after('salesType'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('flagPending') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'flagPending');
        }
    }
}
