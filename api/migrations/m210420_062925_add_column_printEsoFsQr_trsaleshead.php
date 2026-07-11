<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m210420_062925_add_column_printEsoFsQr_trsaleshead
 */
class m210420_062925_add_column_printEsoFsQr_trsaleshead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('printEsoFsQr') === null) {
            $this->addColumn(
                SalesHead::tableName(),
                'printEsoFsQr',
                $this->integer(11)->defaultValue(0)->after('terminalID')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('printEsoFsQr') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'printEsoFsQr');
        }
    }
}
