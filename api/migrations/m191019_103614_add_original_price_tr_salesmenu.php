<?php
use app\models\SalesMenu;
use yii\db\Expression;
use yii\db\Migration;

/**
 * Class m191019_103614_add_original_price_tr_salesmenu
 */
class m191019_103614_add_original_price_tr_salesmenu extends Migration {
    /**
     * {@inheritdoc}
     */
    public function safeUp() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('originalPrice') === null) {
            $this->addColumn(SalesMenu::tableName(), 'originalPrice',
                $this->decimal(20, 4)->notNull()->after('qty'));

            $this->update(SalesMenu::tableName(),
                ['originalPrice' => new Expression('price')]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('originalPrice') !== null) {
            $this->dropColumn(SalesMenu::tableName(), 'originalPrice');
        }
    }

}
