<?php

use yii\db\Migration;
use app\models\VoucherCategory;

/**
 * Class m210309_034300_create_lk_vouchercategory
 */
class m210309_034300_create_lk_vouchercategory extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(VoucherCategory::tableName(),
                true) === null) {
            $this->createTable(VoucherCategory::tableName(),
                [
                'voucherCategoryID' => $this->primaryKey(),
                'voucherCategoryName' => $this->string(50)->notNull(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VoucherCategory::tableName(),
                true) !== null) {
            $this->dropTable(VoucherCategory::tableName());
        }
    }
}
