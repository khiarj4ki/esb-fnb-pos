<?php
use app\models\Menu;
use yii\db\Migration;

/**
 * Class m191112_113508_add_imageurl_ms_menu
 */
class m191112_113508_add_imageurl_ms_menu extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('imageUrl') === null) {
            $this->addColumn(Menu::tableName(), 'imageUrl',
                $this->text()->defaultValue(NULL)->after('flagActive'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('imageUrl') !== null) {
            $this->dropColumn(Menu::tableName(), 'imageUrl');
        }
    }

}
