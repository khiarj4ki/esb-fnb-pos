<?php

use app\models\PosExternalPayment;
use yii\db\Migration;

/**
 * Class m200615_033531_create_lk_posexternalpayment
 */
class m200615_033531_create_lk_posexternalpayment extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PosExternalPayment::tableName(), true) === null) {
            $this->createTable(PosExternalPayment::tableName(),
                    [
                        'posExternalPaymentID' => $this->string(10)->notNull()->append('PRIMARY KEY'),
                        'posExternalPaymentName' => $this->string(50)->notNull(),
                        'posExternalPaymentType' => $this->string(20),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PosExternalPayment::tableName(), true) !== null) {
            $this->dropTable(PosExternalPayment::tableName());
        }
    }
}
