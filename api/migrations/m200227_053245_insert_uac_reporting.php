<?php

use yii\db\Migration;
use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;

/**
 * Class m200227_053245_insert_uac_reporting
 */
class m200227_053245_insert_uac_reporting extends Migration
{
     public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'J7'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'J', 'filterAccessID' => 'J7', 'description' => 'Reporting', 
                    'subNodes' => '', 'action' => 'shift', 'orderID' => '7']);
        }

        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "J7"');
        
        $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                "(posUserRoleID, filterAccessID, hasAccess)" .
                "SELECT posUserRoleID, 'J7', '1' " .
                "FROM " . PosUserRole::tableName());
    }

    public function down()
    {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "J7"');
        $this->delete('ms_posuseraccess', 'filterAccessID = "J7"');
    }
}
