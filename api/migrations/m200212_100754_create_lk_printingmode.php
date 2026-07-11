<?php

use app\models\PrintingMode;
use yii\db\Migration;

/**
 * Class m200212_100754_create_lk_printingmode
 */
class m200212_100754_create_lk_printingmode extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PrintingMode::tableName(), true) === null) {
            $this->createTable(PrintingMode::tableName(),
                [
                    'printingModeID' => $this->primaryKey(),
                    'printingModeName' => $this->string(50)->notNull(),
            ]);
        }
        
        if (!PrintingMode::find()->where(['printingModeName' => 'Standard Printing'])->exists()) {
            $this->insert(PrintingMode::tableName(),
                ['printingModeID' => '1', 'printingModeName' => 'Standard Printing']);
        }

        if (!PrintingMode::find()->where(['printingModeName' => 'Single Menu Printing'])->exists()) {
            $this->insert(PrintingMode::tableName(),
                ['printingModeID' => '2', 'printingModeName' => 'Single Menu Printing']);
        }

        if (!PrintingMode::find()->where(['printingModeName' => 'Qty Menu Printing'])->exists()) {
            $this->insert(PrintingMode::tableName(),
                ['printingModeID' => '3', 'printingModeName' => 'Qty Menu Printing']);
        }
        
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PrintingMode::tableName(), true) !== null) {
            $this->dropTable(PrintingMode::tableName());
        }
    }
}
