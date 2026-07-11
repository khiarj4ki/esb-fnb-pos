<?php

use app\models\HubHost;
use yii\db\Migration;

/**
 * Class m191231_070315_alter_column_paymentmethodid_coano_hub
 */
class m191231_070315_alter_column_paymentmethodid_coano_hub extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(HubHost::tableName(), true)->getColumn('paymentMethodID') !== null) {
            $this->dropColumn(HubHost::tableName(), 'paymentMethodID');
        }

        if ($this->db->getTableSchema(HubHost::tableName(), true)->getColumn('coaNo') !== null) {
            $this->dropColumn(HubHost::tableName(), 'coaNo');
        }

    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(HubHost::tableName(), true)->getColumn('paymentMethodID') == null) {
            $this->addColumn(HubHost::tableName(), 'paymentMethodID', 'INT(11) AFTER `hubName` ');
        }

        if ($this->db->getTableSchema(HubHost::tableName(), true)->getColumn('coaNo') == null) {
            $this->addColumn(HubHost::tableName(), 'coaNo', 'VARCHAR(20) AFTER `paymentMethodID` ');
        }

    }
}
