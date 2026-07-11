<?php
use app\models\Branch;
use yii\db\Expression;
use yii\db\Migration;

/**
 * Class m191019_103603_add_branch_settings_ms_branch
 */
class m191019_103603_add_branch_settings_ms_branch extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('printingCheckerFooter') === null) {
            $this->addColumn(Branch::tableName(), 'printingCheckerFooter',
                $this->string(500)->after('printingFooter'));
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('image') === null) {
            $this->addColumn(Branch::tableName(), 'image',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->after('flagOtherTaxVat'));
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('menuTemplateID') === null) {
            $this->addColumn(Branch::tableName(), 'menuTemplateID',
                $this->integer()->after('image'));

            $this->update(Branch::tableName(),
                ['menuTemplateID' => new Expression('branchID')]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('printingCheckerFooter') !== null) {
            $this->dropColumn(Branch::tableName(), 'printingCheckerFooter');
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('image') !== null) {
            $this->dropColumn(Branch::tableName(), 'image');
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('menuTemplateID') !== null) {
            $this->dropColumn(Branch::tableName(), 'menuTemplateID');
        }
    }

}
