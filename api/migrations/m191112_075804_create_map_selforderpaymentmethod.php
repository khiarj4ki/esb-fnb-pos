<?php
use app\models\MapSelfOrderPaymentMethod;
use yii\db\Migration;

/**
 * Class m191112_075804_create_map_selforderpaymentmethod
 */
class m191112_075804_create_map_selforderpaymentmethod extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MapSelfOrderPaymentMethod::tableName(),
                true) === null) {
            $this->createTable(MapSelfOrderPaymentMethod::tableName(),
                [
                'selfOrderPaymentMethodID' => $this->string(10)->notNull(),
                'branchID' => $this->integer()->notNull(),
                'paymentMethodID' => $this->integer()->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY',
                MapSelfOrderPaymentMethod::tableName(),
                ['selfOrderPaymentMethodID', 'branchID', 'paymentMethodID']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MapSelfOrderPaymentMethod::tableName(),
                true) !== null) {
            $this->dropTable(MapSelfOrderPaymentMethod::tableName());
        }
    }

}
