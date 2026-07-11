<?php
use app\models\MenuTemplateHead;
use yii\db\Migration;

/**
 * Class m191026_034620_add_flag_inclusive_ms_menutemplatehead
 */
class m191026_034620_add_flag_inclusive_ms_menutemplatehead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateHead::tableName(), true)->getColumn('flagInclusive') === null) {
            $this->addColumn(MenuTemplateHead::tableName(), 'flagInclusive',
                $this->tinyInteger(1)->defaultValue(0)->after('notes'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateHead::tableName(), true)->getColumn('flagInclusive') === null) {
            $this->dropColumn(MenuTemplateHead::tableName(), 'flagInclusive');
        }
    }

}
