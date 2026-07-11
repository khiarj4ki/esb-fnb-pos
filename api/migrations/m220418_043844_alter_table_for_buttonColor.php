<?php

use app\models\MenuCategory;
use app\models\MenuCategoryDetail;
use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m220418_043844_alter_table_for_buttonColor
 */
class m220418_043844_alter_table_for_buttonColor extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('buttonColor') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'buttonColor',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->defaultValue(null)->after('flagActive'));
        }

        if ($this->db->getTableSchema(MenuCategory::tableName(), true)->getColumn('buttonColor') === null) {
            $this->addColumn(MenuCategory::tableName(), 'buttonColor',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->defaultValue(null)->after('orderID'));
        }
        
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('buttonColor') === null) {
            $this->addColumn(MenuCategoryDetail::tableName(), 'buttonColor',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->defaultValue(null)->after('orderID'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('buttonColor') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'buttonColor');
        }
        
        if ($this->db->getTableSchema(MenuCategory::tableName(), true)->getColumn('buttonColor') !== null) {
            $this->dropColumn(MenuCategory::tableName(), 'buttonColor');
        }

        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('buttonColor') !== null) {
            $this->dropColumn(MenuCategoryDetail::tableName(), 'buttonColor');
        }
    }
}
