<?php

use app\models\SalesMenuRelated;
use yii\db\Migration;

/**
 * Class m211011_105225_add_column_mainMenuQty_relatedMenyQty_tr_salesmenurelated
 */
class m211011_105225_add_column_mainMenuQty_relatedMenyQty_tr_salesmenurelated extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenuRelated::tableName(), true)->getColumn('mainMenuQty') === null) {
            $this->addColumn(SalesMenuRelated::tableName(), 'mainMenuQty', $this->decimal(20, 4)->defaultValue(0)->after('mainMenuID'));
        }

        if ($this->db->getTableSchema(SalesMenuRelated::tableName(), true)->getColumn('relatedMenuQty') === null) {
            $this->addColumn(SalesMenuRelated::tableName(), 'relatedMenuQty', $this->decimal(20, 4)->defaultValue(0)->after('relatedMenuID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesMenuRelated::tableName(), true)->getColumn('mainMenuQty') !== null) {
            $this->dropColumn(SalesMenuRelated::tableName(), 'mainMenuQty');
        }

        if ($this->db->getTableSchema(SalesMenuRelated::tableName(), true)->getColumn('relatedMenuQty') !== null) {
            $this->dropColumn(SalesMenuRelated::tableName(), 'relatedMenuQty');
        }
    }
}
