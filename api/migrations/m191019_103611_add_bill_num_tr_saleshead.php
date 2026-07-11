<?php
use app\models\SalesHead;
use yii\db\Expression;
use yii\db\Migration;

/**
 * Class m191019_103611_add_bill_num_tr_saleshead
 */
class m191019_103611_add_bill_num_tr_saleshead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('billNum') === null) {
            $this->addColumn(SalesHead::tableName(), 'billNum',
                $this->string(20)->after('salesNum'));

            $this->update(SalesHead::tableName(),
                ['billNum' => new Expression('salesNum')]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('billNum') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'billNum');
        }
    }

}
