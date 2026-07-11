<?php

use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;
use yii\db\Migration;

/**
 * Class m201110_055926_insert_access_deposit_receivable
 */
class m201110_055926_insert_access_deposit_receivable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'A17'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'A', 'filterAccessID' => 'A17', 'description' => 'Deposit Receivable', 
                    'subNodes' => '', 'action' => null, 'orderID' => '17']);
            
            $this->delete(PosUserAccess::tableName(), 'filterAccessID = "A17"');
        
            $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                    "(posUserRoleID, filterAccessID, hasAccess)" .
                    "SELECT posUserRoleID, 'A17', '0' " .
                    "FROM " . PosUserRole::tableName());
        }
    }

    public function down()
    {
        if (PosFilterAccess::find()->where(['filterAccessID' => 'A17'])->exists()) {
            $this->delete('lk_posfilteraccess', 'filterAccessID = "A17"');
            $this->delete('ms_posuseraccess', 'filterAccessID = "A17"');
        }
        
    }
}
