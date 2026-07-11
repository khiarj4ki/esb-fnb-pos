<?php

use app\models\MsNotificationHead;
use yii\db\Migration;

/**
 * Class m210201_085403_alter_table_ms_notificationhead
 */
class m210201_085403_alter_table_ms_notificationhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('createdBy') === null) {
            $this->addColumn(MsNotificationHead::tableName(), 'createdBy',
                $this->string(100)->notNull()->defaultValue('SYSTEM')->after('endDate'));
        }

        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('createdDate') === null) {
            $this->addColumn(MsNotificationHead::tableName(), 'createdDate',
                $this->dateTime()->notNull()->defaultValue(date('Y-m-d H:i:s'))->after('createdBy'));
        }

        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('editedBy') === null) {
            $this->addColumn(MsNotificationHead::tableName(), 'editedBy',
                $this->string(100)->after('createdDate'));
        }

        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('editedDate') === null) {
            $this->addColumn(MsNotificationHead::tableName(), 'editedDate',
                $this->dateTime()->after('editedBy'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('createdBy') !== null) {
            $this->dropColumn(MsNotificationHead::tableName(), 'createdBy');
        }

        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('createdDate') !== null) {
            $this->dropColumn(MsNotificationHead::tableName(), 'createdDate');
        }

        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('editedBy') !== null) {
            $this->dropColumn(MsNotificationHead::tableName(), 'editedBy');
        }

        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true)->getColumn('editedDate') !== null) {
            $this->dropColumn(MsNotificationHead::tableName(), 'editedDate');
        }
    }
}
