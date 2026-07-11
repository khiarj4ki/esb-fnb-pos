<?php

use app\models\Branch;
use yii\db\Migration;

/**
 * Class m200108_034150_alter_column_postaxcalculationid_posothertaxcalculationid
 */
class m200108_034150_alter_column_postaxcalculationid_posothertaxcalculationid extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('posTaxCalculationID') === null) {
            $this->addColumn(Branch::tableName(),
                'posTaxCalculationID',
                $this->integer()->defaultValue(0)->after('menuTemplateID'));
        }
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('posOtherTaxCalculationID') === null) {
            $this->addColumn(Branch::tableName(),
                'posOtherTaxCalculationID',
                $this->integer()->defaultValue(0)->after('posTaxCalculationID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('posTaxCalculationID') !== null) {
            $this->dropColumn(Branch::tableName(),
                'posTaxCalculationID');
        }
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('posOtherTaxCalculationID') !== null) {
            $this->dropColumn(Branch::tableName(),
                'posOtherTaxCalculationID');
        }
    }
}
