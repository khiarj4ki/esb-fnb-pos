<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m210201_024511_add_column_promotionvouchercode_salesmenu
 */
class m210201_024511_add_column_promotionvouchercode_salesmenu extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('promotionVoucherCode') === null) {
            $this->addColumn(SalesMenu::tableName(), 'promotionVoucherCode',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->null()->after('menuPromotionID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('promotionVoucherCode') !== null) {
            $this->dropColumn(SalesMenu::tableName(), 'promotionVoucherCode');
        }
    }
}
