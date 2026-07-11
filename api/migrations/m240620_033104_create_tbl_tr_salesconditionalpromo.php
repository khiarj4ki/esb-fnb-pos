<?php

use app\models\SalesConditionalPromo;
use yii\db\Migration;

/**
 * Class m240620_033104_create_tbl_tr_salesconditionalpromo
 */
class m240620_033104_create_tbl_tr_salesconditionalpromo extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesConditionalPromo::tableName(), true) === null) {
            $this->createTable(SalesConditionalPromo::tableName(),
                [
                    'salesNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                    'conditionalPromoID' => $this->integer(11)->notNull()
            ]);

            $this->createIndex('idx_tr_salesconditionalpromo_conditionalPromoID', SalesConditionalPromo::tableName(), 'conditionalPromoID');
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesConditionalPromo::tableName(), true) !== null) {
            $this->dropTable(SalesConditionalPromo::tableName());
        }
    }
}
