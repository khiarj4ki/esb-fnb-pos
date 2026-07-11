<?php

use app\models\MapBranchVisitPurpose;
use app\models\MapVisitPurposePaymentMethod;
use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m210723_092640_create_map_visitpurposepaymentmethod
 */
class m210723_092640_create_map_visitpurposepaymentmethod extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapVisitPurposePaymentMethod::tableName(), true) === null) {
            $this->createTable(
                MapVisitPurposePaymentMethod::tableName(),
                [
                    'paymentMethodID' => $this->integer()->notNull(),
                    'visitPurposeID' => $this->integer()->notNull()
                ]
            );

            $this->addPrimaryKey(
                'PRIMARYKEY',
                MapVisitPurposePaymentMethod::tableName(),
                ['paymentMethodID', 'visitPurposeID']
            );

            $this->execute(
                "INSERT INTO " . MapVisitPurposePaymentMethod::tableName() . " " .
                    "SELECT a.paymentMethodID, b.visitPurposeID " .
                    "FROM " . PaymentMethod::tableName() . " a " .
                    "JOIN " . MapBranchVisitPurpose::tableName() . " b ON 1=1 " .
                    "GROUP BY a.paymentMethodID, b.visitPurposeID;"
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(MapVisitPurposePaymentMethod::tableName(), true) !== null) {
            $this->dropTable(MapVisitPurposePaymentMethod::tableName());
        }
    }
}
