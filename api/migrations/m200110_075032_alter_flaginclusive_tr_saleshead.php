<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200110_075032_alter_flaginclusive_tr_saleshead
 */
class m200110_075032_alter_flaginclusive_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagInclusive') === null) {
            $this->addColumn(SalesHead::tableName(),
                'flagInclusive',
                $this->integer(1)->defaultValue(0)->after('promotionID'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagInclusive') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'flagInclusive');
        }
    }
}
