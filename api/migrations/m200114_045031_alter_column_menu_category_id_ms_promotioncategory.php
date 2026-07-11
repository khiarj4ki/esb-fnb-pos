<?php

use app\models\PromotionCategory;
use yii\db\Migration;

/**
 * Class m200114_045031_alter_column_menu_category_id_ms_promotioncategory
 */
class m200114_045031_alter_column_menu_category_id_ms_promotioncategory extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionCategory::tableName(), true)->getColumn('menuCategoryID') !== null) {
            $this->alterColumn(PromotionCategory::tableName(),
                'menuCategoryID', $this->integer()->null());
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        
    }
}
