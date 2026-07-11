<?php

use app\models\TableSection;
use yii\db\Migration;

/**
 * Class m200214_093305_alter_column_image_ms_tablesection
 */
class m200214_093305_alter_column_image_ms_tablesection extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(TableSection::tableName(), true)->getColumn('image') === null) {
            $this->addColumn(TableSection::tableName(), 'image',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->after('branchID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(TableSection::tableName(), true)->getColumn('image') !== null) {
            $this->dropColumn(TableSection::tableName(),
                'image');
        }
    }
}
