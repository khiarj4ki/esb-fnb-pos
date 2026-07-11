<?php

use app\models\SalesShiftPaymentHead;
use app\models\ShiftLogDetail;
use yii\db\Migration;

/**
 * Class m210322_032343_update_old_data_ba_online
 */
class m210322_032343_update_old_data_ba_online extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $salesShiftPaymentHeadTableName = SalesShiftPaymentHead::tableName();
        $shiftLogDetailTableName = ShiftLogDetail::tableName();

        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('createdBy') !== null) {
            Yii::$app->db->createCommand("UPDATE $salesShiftPaymentHeadTableName a
                JOIN $shiftLogDetailTableName b ON a.shiftLogDetailID = b.ID
                SET a.createdBy = b.shiftUsername
                WHERE a.createdBy IS NULL")->execute();
        }

        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('submittedBy') !== null) {
            Yii::$app->db->createCommand("UPDATE $salesShiftPaymentHeadTableName
                SET submittedBy = createdBy
                WHERE submittedBy IS NULL")->execute();
        }
    }

    public function down()
    {

    }
}
