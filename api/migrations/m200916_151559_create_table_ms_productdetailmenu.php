<?php

use yii\db\Migration;
use app\models\ProductDetailMenu;

/**
 * Class m200916_151559_create_table_ms_productdetailmenu
 */
class m200916_151559_create_table_ms_productdetailmenu extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(ProductDetailMenu::tableName(),
                true) === null) {
            $this->createTable(ProductDetailMenu::tableName(),
                [
                'productID' => $this->integer()->notNull(),
                'productDetailID' => $this->integer()->notNull(),
                'menuID' => $this->integer()->notNull(),
                'convertionQty' => $this->decimal(20, 4),
                'PRIMARY KEY(productDetailID, menuID)',
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(ProductDetailMenu::tableName(),
                true) !== null) {
            $this->dropTable(ProductDetailMenu::tableName());
        }
    }
}
