<?php

use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;
use yii\db\Migration;

/**
 * Class m201102_024226_insert_access_orderfee_deliverycost
 */
class m201102_024226_insert_access_orderfee_deliverycost extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'A15'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'A', 'filterAccessID' => 'A15', 'description' => 'Order Fee', 
                    'subNodes' => '', 'action' => null, 'orderID' => '15']);
            
            $this->delete(PosUserAccess::tableName(), 'filterAccessID = "A15"');
        
            $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                    "(posUserRoleID, filterAccessID, hasAccess)" .
                    "SELECT posUserRoleID, 'A15', '1' " .
                    "FROM " . PosUserRole::tableName());
        }

        if (!PosFilterAccess::find()->where(['filterAccessID' => 'A16'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'A', 'filterAccessID' => 'A16', 'description' => 'Delivery Cost', 
                    'subNodes' => '', 'action' => null, 'orderID' => '16']);
            
            $this->delete(PosUserAccess::tableName(), 'filterAccessID = "A16"');
        
            $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                    "(posUserRoleID, filterAccessID, hasAccess)" .
                    "SELECT posUserRoleID, 'A16', '1' " .
                    "FROM " . PosUserRole::tableName());
        }
    }

    public function down()
    {
        if (PosFilterAccess::find()->where(['filterAccessID' => 'A15'])->exists()) {
            $this->delete('lk_posfilteraccess', 'filterAccessID = "A15"');
            $this->delete('ms_posuseraccess', 'filterAccessID = "A15"');
        }
        
        if (PosFilterAccess::find()->where(['filterAccessID' => 'A16'])->exists()) {
            $this->delete('lk_posfilteraccess', 'filterAccessID = "A16"');
            $this->delete('ms_posuseraccess', 'filterAccessID = "A16"');
        }
        
    }
}
