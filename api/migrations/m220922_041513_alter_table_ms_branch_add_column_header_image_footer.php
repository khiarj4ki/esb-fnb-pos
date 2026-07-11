<?php

use app\models\Branch;
use yii\db\Migration;

/**
 * Class m220922_041513_alter_table_ms_branch_add_column_header_image_footer
 */
class m220922_041513_alter_table_ms_branch_add_column_header_image_footer extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('flagHeaderImageOriginalSize') === null) {
            $this->addColumn(Branch::tableName(), 'flagHeaderImageOriginalSize', $this->tinyInteger(1)->notNull()->defaultValue(0)->after('image'));
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('imageFooter') === null) {
            $this->addColumn(Branch::tableName(), 'imageFooter', $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->after('flagHeaderImageOriginalSize'));
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('flagFooterImageOriginalSize') === null) {
            $this->addColumn(Branch::tableName(), 'flagFooterImageOriginalSize', $this->tinyInteger(1)->notNull()->defaultValue(0)->after('imageFooter'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('flagHeaderImageOriginalSize') !== null) {
            $this->dropColumn(Branch::tableName(), 'flagHeaderImageOriginalSize');
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('imageFooter') !== null) {
            $this->dropColumn(Branch::tableName(), 'imageFooter');
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('flagFooterImageOriginalSize') !== null) {
            $this->dropColumn(Branch::tableName(), 'flagFooterImageOriginalSize');
        }
    }
}
