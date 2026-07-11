<?php

use app\models\TentCard;
use yii\db\Migration;
use yii\db\sqlite\Schema;

/**
 * Class m200317_093440_create_ms_tendcard
 */
class m200317_093440_create_ms_tendcard extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(TentCard::tableName(), true) === null) {
            $this->createTable(TentCard::tableName(),
                [
                    'tentCardID' => Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                    'branchID' => $this->integer()->notNull(),
                    'name' => $this->string(100)->notNull(),
                    'image' => $this->text(),
                    'flagFeatured' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()->defaultValue('0'),
                    'flagActive' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()->defaultValue('0'),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(100)->notNull(),
                    'editedDate' => $this->dateTime(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(TentCard::tableName(), true) !== null) {
            $this->dropTable(TentCard::tableName());
        }
    }
}
