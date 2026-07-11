<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200226_050115_add_column_remarks_tr_saleshead
 */
class m200226_050115_add_column_remarks_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('remarks') === null) {
            $this->addColumn(SalesHead::tableName(), 'remarks',
                $this->string(200)->after('additionalInfo'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('remarks') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'remarks');
        }
    }
}
