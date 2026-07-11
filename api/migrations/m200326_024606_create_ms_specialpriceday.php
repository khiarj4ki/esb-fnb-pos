<?php

use app\models\SpecialPriceDay;
use yii\db\Migration;

/**
 * Class m200326_024606_create_ms_specialpriceday
 */
class m200326_024606_create_ms_specialpriceday extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SpecialPriceDay::tableName(), true) === null) {
            $this->createTable(SpecialPriceDay::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'specialPriceID' => $this->integer()->notNull(),
                    'dayID' => $this->integer()->notNull()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SpecialPriceDay::tableName(), true) !== null) {
            $this->dropTable(SpecialPriceDay::tableName());
        }
    }
}
