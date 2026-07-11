<?php

use app\models\SpecialPriceHead;
use yii\db\Migration;

/**
 * Class m200326_024557_create_ms_specialpricehead
 */
class m200326_024557_create_ms_specialpricehead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SpecialPriceHead::tableName(), true) === null) {
            $this->createTable(SpecialPriceHead::tableName(),
                [
                    'specialPriceID' => $this->primaryKey(),
                    'startDate' => $this->date()->notNull(),
                    'endDate' => $this->date()->notNull(),
                    'menuTemplateID' => $this->integer()->notNull(),
                    'notes' => $this->string(100)->defaultValue(NULL),
                    'flagActive' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull(),
                    'createdBy' => $this->string(50)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(50),
                    'editedDate' => $this->dateTime(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SpecialPriceHead::tableName(), true) !== null) {
            $this->dropTable(SpecialPriceHead::tableName());
        }
    }
}
