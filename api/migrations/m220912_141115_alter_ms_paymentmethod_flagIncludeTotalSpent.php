<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m220912_141115_alter_ms_paymentmethod_flagIncludeTotalSpent
 */
class m220912_141115_alter_ms_paymentmethod_flagIncludeTotalSpent extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {   
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagIncludeTotalSpent') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'flagIncludeTotalSpent',
                $this->tinyInteger(1)->defaultValue(0)->after('flagEdcActive')
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagIncludeTotalSpent') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'flagIncludeTotalSpent');
        }
    }
}
