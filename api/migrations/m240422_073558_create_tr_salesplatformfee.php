<?php

use app\models\SalesPlatformFee;
use yii\db\Migration;

/**
 * Class m240422_073558_create_tr_salesplatformfee
 */
class m240422_073558_create_tr_salesplatformfee extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesPlatformFee::tableName(), true) === null) {
            $this->createTable(SalesPlatformFee::tableName(), [
                'id' => $this->primaryKey(),
                'orderID' => $this->string(20),
                'salesNum' => $this->string(20),
                'platformFeeTypeID' => $this->integer(11)->notNull(),
                'feeNameEN' => $this->string(200),
                'feeNameID' => $this->string(200),
                'percentage' => $this->decimal(20,4),
                'amount' => $this->decimal(20,4)
            ]);

            $this->createIndex(
                'salesNum_INDEX',
                SalesPlatformFee::tableName(),
                'salesNum'
            );

        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesPlatformFee::tableName(), true) !== null) {
            $this->dropTable(SalesPlatformFee::tableName());
        }
    }
}
