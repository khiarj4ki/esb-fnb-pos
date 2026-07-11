<?php

use app\models\PromotionCategory;
use yii\db\Migration;

/**
 * Class m200107_112537_alter_column_menucategorydetailid_menuid
 */
class m200107_112537_alter_column_menucategorydetailid_menuid extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionCategory::tableName(), true)->getColumn('menuCategoryDetailID') === null) {
            $this->addColumn(PromotionCategory::tableName(),
                'menuCategoryDetailID',
                $this->integer()->defaultValue(NULL)->after('menuCategoryID'));
        }
        if ($this->db->getTableSchema(PromotionCategory::tableName(), true)->getColumn('menuID') === null) {
            $this->addColumn(PromotionCategory::tableName(),
                'menuID',
                $this->integer()->defaultValue(NULL)->after('menuCategoryDetailID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionCategory::tableName(), true)->getColumn('menuCategoryDetailID') !== null) {
            $this->dropColumn(MsProduct::tableName(),
                'menuCategoryID');
        }
        if ($this->db->getTableSchema(PromotionCategory::tableName(), true)->getColumn('menuID') !== null) {
            $this->dropColumn(MsProduct::tableName(),
                'menuCategoryDetailID');
        }
    }
}
