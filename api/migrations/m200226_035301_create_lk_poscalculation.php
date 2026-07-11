<?php
use app\models\PosCalculation;
use yii\db\Migration;

/**
 * Class m200226_035301_create_lk_poscalculation
 */
class m200226_035301_create_lk_poscalculation extends Migration {
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PosCalculation::tableName(),
                true) === null) {
            $this->createTable(PosCalculation::tableName(),
                [
                'posCalculationID' => $this->integer()->notNull()->append('PRIMARY KEY'),
                'posCalculationName' => $this->string(50),
            ]);

            $this->batchInsert(PosCalculation::tableName(),
                ['posCalculationID', 'posCalculationName'],
                [
                    [1, 'Before Discount'],
                    [2, 'After Discount']
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PosCalculation::tableName(),
                true) !== null) {
            $this->dropTable(PosCalculation::tableName());
        }
    }

}
