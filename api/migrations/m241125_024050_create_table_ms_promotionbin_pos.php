<?php

use app\models\PromotionBin;
use yii\db\Migration;

/**
 * Class m241125_024050_create_table_ms_promotionbin_pos
 */
class m241125_024050_create_table_ms_promotionbin_pos extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionBin::tableName(), true) === null) {
            $this->createTable(PromotionBin::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'promotionID' => $this->integer(11)->notNull(),
                    'bankIdentificationNumber' => $this->string(6)->notNull()
            ]);

            $this->createIndex(
                'idx_ms_promotionbin',
                PromotionBin::tableName(),
                ['ID','promotionID','bankIdentificationNumber']
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionBin::tableName(), true) !== null) {
            $this->dropTable(PromotionBin::tableName());
        }
    }
}
