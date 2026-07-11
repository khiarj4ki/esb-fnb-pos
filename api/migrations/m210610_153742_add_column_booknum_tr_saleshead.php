<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m210610_153742_add_column_booknum_tr_saleshead
 */
class m210610_153742_add_column_booknum_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('bookNum') === null) {
            $this->addColumn(SalesHead::tableName(), 'bookNum',
                $this->string(20)->after('billNum')->null());
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('bookNum') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'bookNum');
        }
    }
}
