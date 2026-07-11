<?php

use app\models\PromotionTime;
use yii\db\Migration;

/**
 * Class m200205_070919_create_ms_promotiontime
 */
class m200205_070919_create_ms_promotiontime extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionTime::tableName(), true) === null) {
            $this->createTable(PromotionTime::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'promotionID' => $this->integer()->notNull(),
                    'startTime' => $this->time()->notNull(),
                    'endTime' => $this->time()->notNull()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionTime::tableName(), true) !== null) {
            $this->dropTable(PromotionTime::tableName());
        }
    }
}
