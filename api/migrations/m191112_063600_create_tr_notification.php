<?php
use app\models\Notification;
use yii\db\Migration;

/**
 * Class m191112_063600_create_tr_notification
 */
class m191112_063600_create_tr_notification extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Notification::tableName(), true) === null) {
            $this->createTable(Notification::tableName(),
                [
                'tableID' => $this->integer()->notNull(),
                'action' => $this->string(50)->notNull(),
                'createdDate' => $this->dateTime()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', Notification::tableName(),
                ['tableID', 'action']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Notification::tableName(), true) !== null) {
            $this->dropTable(Notification::tableName());
        }
    }

}
