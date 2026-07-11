<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m220921_021359_alter_tr_saleshead_change_collation_external_member_name
 */
class m220921_021359_alter_tr_saleshead_change_collation_external_member_name extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalMemberName') !== null) {
            $schema = $this->getDb()->getSchema()->createColumnSchemaBuilder('CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->alterColumn(SalesHead::tableName(), 'externalMemberName', $this->string(100)->append($schema));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalMemberName') === null) {
            $schema = $this->getDb()->getSchema()->createColumnSchemaBuilder('CHARACTER SET utf8 COLLATE utf8_unicode_ci');
            $this->alterColumn(SalesHead::tableName(), 'externalMemberName', $this->string(100)->append($schema));
        }
    }
}
