<?php

use app\models\MapBranchVisitPurpose;
use yii\db\Migration;
use yii\db\Expression;

/**
 * Class m191205_092535_add_flag_kiosk_map_branchvisitpurpose
 */
class m191205_092535_add_flag_kiosk_map_branchvisitpurpose extends Migration
{
    /**
    * {@inheritdoc}
    */
    public function up()
    {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('flagKiosk') === null) {
            $this->addColumn(
                MapBranchVisitPurpose::tableName(),
                'flagKiosk',
                $this->tinyInteger(1)->after('flagSelfOrder')
            );
            $this->update(MapBranchVisitPurpose::tableName(), [
                'flagKiosk' => new Expression('flagSelfOrder')
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('flagKiosk') !== null) {
            $this->dropColumn(
                MapBranchVisitPurpose::tableName(),
                'flagKiosk'
            );
        }
    }
}
