<?php
use app\models\Menu;
use yii\db\Migration;

/**
 * Class m191112_114958_add_description_ms_menu
 */
class m191112_114958_add_description_ms_menu extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('description') === null) {
            $this->addColumn(Menu::tableName(), 'description',
                $this->text()->defaultValue(NULL)->after('imageUrl'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('description') !== null) {
            $this->dropColumn(Menu::tableName(), 'description');
        }
    }

}
