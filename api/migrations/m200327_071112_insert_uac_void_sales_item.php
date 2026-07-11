<?php

use yii\db\Migration;
use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;

/**
 * Class m200327_071112_insert_uac_void_sales_item
 */
class m200327_071112_insert_uac_void_sales_item extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'B7'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                ['posAccessID' => 'B', 'filterAccessID' => 'B7', 'description' => 'Void Sales Item', 
                    'subNodes' => '', 'action' => 'sales/menu-void', 'orderID' => '7']);
        }

        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "B7"');
        
        $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                "(posUserRoleID, filterAccessID, hasAccess)" .
                "SELECT posUserRoleID, 'B7', '1' " .
                "FROM " . PosUserRole::tableName());
    }

    public function down()
    {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "B7"');
        $this->delete('ms_posuseraccess', 'filterAccessID = "B7"');
    }
}
