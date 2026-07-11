<?php

use app\models\VisitorType;
use yii\db\Migration;

/**
 * Class m201127_093408_create_table_ms_visitortype
 */
class m201127_093408_create_table_ms_visitortype extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(VisitorType::tableName(),
                true) === null) {
            $this->createTable(VisitorType::tableName(),
                [
                    'visitorTypeID' => $this->primaryKey(),
                    'visitorTypeName' => $this->string(50)->notNull(),
                    'flagActive' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()->defaultValue('0'),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VisitorType::tableName(),
                true) !== null) {
            $this->dropTable(VisitorType::tableName());
        }
    }
}
