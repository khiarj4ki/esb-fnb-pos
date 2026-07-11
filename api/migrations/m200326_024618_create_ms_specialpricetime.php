<?php

use app\models\SpecialPriceTime;
use yii\db\Migration;

/**
 * Class m200326_024618_create_ms_specialpricetime
 */
class m200326_024618_create_ms_specialpricetime extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SpecialPriceTime::tableName(), true) === null) {
            $this->createTable(SpecialPriceTime::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'specialPriceID' => $this->integer()->notNull(),
                    'startTime' => $this->time(0)->notNull(),
                    'endTime' => $this->time(0)->notNull(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SpecialPriceTime::tableName(), true) !== null) {
            $this->dropTable(SpecialPriceTime::tableName());
        }
    }
}
