<?php

use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;
use yii\db\Migration;

/**
 * Class m201118_075604_insert_access_promotion_authorization
 */
class m201118_075604_insert_access_promotion_authorization extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'A18'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'A', 'filterAccessID' => 'A18',
                    'description' => 'Promotion Authorization', 
                    'subNodes' => '', 'action' => null, 'orderID' => '18']);
            
            $this->delete(PosUserAccess::tableName(), 'filterAccessID = "A18"');
        
            $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                    "(posUserRoleID, filterAccessID, hasAccess)" .
                    "SELECT posUserRoleID, 'A18', '0' " .
                    "FROM " . PosUserRole::tableName());
        }
    }

    public function down()
    {
        if (PosFilterAccess::find()->where(['filterAccessID' => 'A18'])->exists()) {
            $this->delete('lk_posfilteraccess', 'filterAccessID = "A18"');
            $this->delete('ms_posuseraccess', 'filterAccessID = "A18"');
        }
        
    }
}
