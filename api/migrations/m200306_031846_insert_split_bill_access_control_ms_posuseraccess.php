<?php

use yii\db\Migration;
use app\models\PosUserAccess;

/**
 * Class m200306_031846_insert_split_bill_access_control_ms_posuseraccess
 */
class m200306_031846_insert_split_bill_access_control_ms_posuseraccess extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $uacs = PosUserAccess::find()->indexBy('posUserRoleID')->all();
        foreach ($uacs as $uac){
            if (!PosUserAccess::find()->where(['filterAccessID' => 'A12', 'posUserRoleID' => $uac['posUserRoleID']])->exists()) {
                $this->execute("INSERT INTO " . PosUserAccess::tableName() . " (posUserRoleID, filterAccessID, hasAccess) " .
                    "VALUES(" . $uac['posUserRoleID'] . ", 'A12', 1)");
            }
        }
    }

    public function down()
    {
        $this->delete(PosUserAccess::tableName(), ['filterAccessID' => 'A12']);
    }
}
