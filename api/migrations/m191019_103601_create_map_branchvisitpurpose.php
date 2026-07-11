<?php
use app\models\Branch;
use app\models\MapBranchVisitPurpose;
use app\models\Setting;
use app\models\VisitPurpose;
use yii\db\Migration;

/**
 * Class m191019_103601_create_map_branchvisitpurpose
 */
class m191019_103601_create_map_branchvisitpurpose extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true) === null) {
            $this->createTable(MapBranchVisitPurpose::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'branchID' => $this->integer(),
                    'visitPurposeID' => $this->integer(),
                    'additionalTaxValue' => $this->decimal(20, 4),
                    'flagOtherTaxVat' => $this->tinyInteger(1),
                    'taxValue' => $this->decimal(20, 4),
                    'menuTemplateID' => $this->integer(),
            ]);

            $this->execute('INSERT INTO ' . MapBranchVisitPurpose::tableName() . ' ' .
                'SELECT NULL, b.branchID, a.visitPurposeID, b.additionalTaxValue, b.flagOtherTaxVat, c.value1, b.branchID ' .
                'FROM ' . VisitPurpose::tableName() . ' a JOIN ' . Branch::tableName() . ' b ON 1 = 1 ' .
                'JOIN ' . Setting::tableName() . " c ON c.key1 = 'Vat' AND c.key2 = 'Value'");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true) !== null) {
            $this->dropTable(MapBranchVisitPurpose::tableName());
        }
    }

}
