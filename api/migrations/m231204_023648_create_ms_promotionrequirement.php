<?php

use app\models\PromotionRequirement;
use yii\db\Migration;

/**
 * Class m231204_023648_create_ms_promotionrequirement
 */
class m231204_023648_create_ms_promotionrequirement extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionRequirement::tableName(), true) === null) {
            $this->createTable(
                PromotionRequirement::tableName(),
                [
                    'ID' => $this->integer(11)->notNull()->append('AUTO_INCREMENT PRIMARY KEY'),
                    'promotionID' => $this->integer(11)->notNull(),
                    'menuCategoryID' => $this->integer(11)->null(),
                    'menuCategoryDetailID' => $this->integer(11)->null(),
                    'menuID' => $this->integer(11)->null(),
                    'reqValue' => $this->decimal(20, 4)->null(),
                    'reqQty' => $this->decimal(20, 4)->null()
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(PromotionRequirement::tableName(), true) !== null) {
            $this->dropTable(PromotionRequirement::tableName());
        }
    }
}
