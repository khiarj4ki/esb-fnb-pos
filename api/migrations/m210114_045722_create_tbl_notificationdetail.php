<?php

use app\models\MsNotificationDetail;
use yii\db\Migration;

/**
 * Class m210114_045722_create_tbl_notificationdetail
 */
class m210114_045722_create_tbl_notificationdetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MsNotificationDetail::tableName(), true) === null) {
            $this->createTable(MsNotificationDetail::tableName(),
                [
                    'notificationDetailID' => $this->integer(11)->notNull(),
                    'notificationID' => $this->integer(11)->notNull(),
                    'branchID' => $this->integer(11)->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', MsNotificationDetail::tableName(),
                ['notificationDetailID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MsNotificationDetail::tableName(), true) !== null) {
            $this->dropTable(MsNotificationDetail::tableName());
        }
    }
}
