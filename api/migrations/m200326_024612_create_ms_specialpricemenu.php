<?php

use app\models\SpecialPriceMenu;
use yii\db\Migration;

/**
 * Class m200326_024612_create_ms_specialpricemenu
 */
class m200326_024612_create_ms_specialpricemenu extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SpecialPriceMenu::tableName(), true) === null) {
            $this->createTable(SpecialPriceMenu::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'specialPriceID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'price' => $this->decimal(20,4)->notNull(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SpecialPriceMenu::tableName(), true) !== null) {
            $this->dropTable(SpecialPriceMenu::tableName());
        }
    }
}
