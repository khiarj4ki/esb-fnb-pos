<?php

use app\models\SalesPromotionBin;
use yii\db\Migration;

/**
 * Class m241126_040131_create_tr_salespromotionbin
 */
class m241126_040131_create_tr_salespromotionbin extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesPromotionBin::tableName(), true) === null) {
            $this->createTable(SalesPromotionBin::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'promotionID' => $this->integer(11)->notNull(),
                    'bankIdentificationNumber' => $this->string(6)->notNull(),
                    'salesNum' => $this->string(20)->notNull(),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesPromotionBin::tableName(), true) !== null) {
            $this->dropTable(SalesPromotionBin::tableName());
        }
        return false;
    }
}
