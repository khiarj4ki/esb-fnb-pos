<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m210520_040402_add_column_externalMemberName_salesHead
 */
class m210520_040402_add_column_externalMemberName_salesHead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalMemberName') === null) {
            $this->addColumn(SalesHead::tableName(), 'externalMemberName',
                $this->string(100)->after('flagExternalCardID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalMemberName') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'externalMemberName');
        }
    }
}
