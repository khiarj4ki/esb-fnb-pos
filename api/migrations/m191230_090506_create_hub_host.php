<?php

use app\models\HubHost;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m191230_090506_create_hub_host
 */
class m191230_090506_create_hub_host extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(HubHost::tableName(), true) === null) {
            $this->createTable(HubHost::tableName(),
                [
                'hubID' => Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                'serverID' => $this->integer()->notNull(),
                'hubName' => $this->string(50)->notNull(),
                'paymentMethodID' => $this->integer()->notNull(),
                'coaNo' => $this->string(20)->notNull(),
                'companyAuthKey' => $this->string(50)->notNull(),
                'flagPrimary' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()->defaultValue('0'),
                'flagActive' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()->defaultValue('0'),
                'createdBy' => $this->string(100)->notNull(),
                'createdDate' => $this->dateTime()->notNull(),
                'editedBy' => $this->string(100),
                'editedDate' => $this->dateTime()
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(HubHost::tableName(), true) !== null) {
            $this->dropTable(HubHost::tableName());
        }
    }
}
