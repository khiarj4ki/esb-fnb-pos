<?php

use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use app\models\PosUserRole;
use yii\db\Migration;

/**
 * Class m200903_022804_add_access_backdate_edit_payment_void_sales
 */
class m200903_022804_add_access_backdate_edit_payment_void_sales extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'B8'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),[
                'posAccessID' => 'B',
                'filterAccessID' => 'B8',
                'description' => 'Backdate Edit Payment',
                'subNodes' => '/edit-payment',
                'action' => '',
                'orderID' => 8
            ]);
        }

        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "B8"');
        
        $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                "(posUserRoleID, filterAccessID, hasAccess)" .
                "SELECT posUserRoleID, 'B8', 1 " .
                "FROM " . PosUserRole::tableName());
        
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'B9'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),[
                'posAccessID' => 'B',
                'filterAccessID' => 'B9',
                'description' => 'Backdate Void Sales',
                'subNodes' => '',
                'action' => 'sales/void',
                'orderID' => 9
            ]);
        }

        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "B9"');
        
        $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                "(posUserRoleID, filterAccessID, hasAccess)" .
                "SELECT posUserRoleID, 'B9', 1 " .
                "FROM " . PosUserRole::tableName());
        
        if (!PosFilterAccess::find()->where(['filterAccessID' => 'B10'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),[
                'posAccessID' => 'B',
                'filterAccessID' => 'B10',
                'description' => 'Backdate Void Sales Item',
                'subNodes' => '',
                'action' => 'sales/menu-void',
                'orderID' => 10
            ]);
        }

        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "B10"');
        
        $this->execute("INSERT INTO " . PosUserAccess::tableName() . " " .
                "(posUserRoleID, filterAccessID, hasAccess)" .
                "SELECT posUserRoleID, 'B10', 1 " .
                "FROM " . PosUserRole::tableName());
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "B8"');
        $this->delete('lk_posfilteraccess', 'filterAccessID = "B9"');
        $this->delete('lk_posfilteraccess', 'filterAccessID = "B10"');
        $this->delete('ms_posuseraccess', 'filterAccessID = "B8"');
        $this->delete('ms_posuseraccess', 'filterAccessID = "B9"');
        $this->delete('ms_posuseraccess', 'filterAccessID = "B10"');
    }
}
