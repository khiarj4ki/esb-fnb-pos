<?php

use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m200324_044241_add_column_orderid_ms_menutemplatedetail
 */
class m200324_044241_add_column_orderid_ms_menutemplatedetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('orderID') === null) {
            $this->addColumn(MenuTemplateDetail::tableName(), 'orderID',
                $this->integer(11)->after('price'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('orderID') !== null) {
            $this->dropColumn(MenuTemplateDetail::tableName(),
                'orderID');
        }
    }
}
