<?php
use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m191019_103607_add_settings_ms_paymentmethod
 */
class m191019_103607_add_settings_ms_paymentmethod extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('parentID') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'parentID',
                $this->integer()->notNull()->defaultValue(0)->after('branchID'));
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('printedCount') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'printedCount',
                $this->integer()->notNull()->defaultValue(2)->after('coaNo'));
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagOpenCashdrawer') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'flagOpenCashdrawer',
                $this->boolean()->notNull()->defaultValue(1)->after('printedCount'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('parentID') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'parentID');
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('printedCount') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'printedCount');
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagOpenCashdrawer') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'flagOpenCashdrawer');
        }
    }

}
