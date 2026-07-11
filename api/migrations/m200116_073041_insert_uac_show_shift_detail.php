<?php

use yii\db\Migration;
use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;

/**
 * Class m200116_073041_insert_uac_show_shift_detail
 */
class m200116_073041_insert_uac_show_shift_detail extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'F5'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'F', 'filterAccessID' => 'F5', 'description' => 'Show Shift Detail', 
                    'subNodes' => '', 'action' => 'shift', 'orderID' => '5']);
        }

        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "F5"');
        
        $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                "(posUserRoleID, filterAccessID, hasAccess)" .
                "SELECT posUserRoleID, 'F5', '1' " .
                "FROM " . PosUserRole::tableName());
    }

    public function down()
    {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "F5"');
        $this->delete('ms_posuseraccess', 'filterAccessID = "F5"');
    }
}
