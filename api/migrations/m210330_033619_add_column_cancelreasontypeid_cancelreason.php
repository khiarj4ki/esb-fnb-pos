<?php

use app\models\CancelReason;
use yii\db\Migration;

/**
 * Class m210330_033619_add_column_cancelreasontypeid_cancelreason
 */
class m210330_033619_add_column_cancelreasontypeid_cancelreason extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(CancelReason::tableName(), true)->getColumn('cancelReasonTypeID') === null) {
            $this->addColumn(CancelReason::tableName(), 'cancelReasonTypeID',$this->integer(11)->after('cancelReasonDesc'));
        }

        $sql = "UPDATE ".CancelReason::tableName()."
            SET cancelReasonTypeID = 1";

        $this->execute($sql);
    }

    public function down()
    {
        if ($this->db->getTableSchema(CancelReason::tableName(), true)->getColumn('cancelReasonTypeID') !== null) {
            $this->dropColumn(CancelReason::tableName(), 'cancelReasonTypeID');
        }
    }
}
