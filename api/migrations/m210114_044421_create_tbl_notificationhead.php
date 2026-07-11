<?php

use app\models\MsNotificationHead;
use yii\db\Migration;

/**
 * Class m210114_044421_create_tbl_notificationhead
 */
class m210114_044421_create_tbl_notificationhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true) === null) {
            $this->createTable(MsNotificationHead::tableName(),
                [
                    'notificationID' => $this->integer(11)->notNull(),
                    'notificationTitle' => $this->string(100)->notNull(),
                    'notificationText' => $this->text()->notNull(),
                    'startDate' => $this->dateTime()->notNull(),
                    'endDate' => $this->dateTime()->notNull()
            ]);
            $this->addPrimaryKey('PRIMARYKEY', MsNotificationHead::tableName(),
                ['notificationID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MsNotificationHead::tableName(), true) !== null) {
            $this->dropTable(MsNotificationHead::tableName());
        }
    }
}
