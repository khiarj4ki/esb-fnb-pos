<?php
use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m191019_103602_add_action_lk_posfilteraccess
 */
class m191019_103602_add_action_lk_posfilteraccess extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PosFilterAccess::tableName(), true)->getColumn('action') === null) {
            $this->addColumn(PosFilterAccess::tableName(), 'action',
                $this->text()->after('subNodes'));

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'order/book-table,order/view,table,table/use,order/update,order/print-order,order/print-checker,order/print-all-checker,order/request-bill,order/print-bill,order/take-away,order/index-take-away,order/request-bill,user/check-session,user/login,user/get-user,user/logout,user/change-password,menu,voucher,voucher/validate'],
                "posAccessID = 'A' AND filterAccessID = 'A1'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'order/merge'],
                "posAccessID = 'A' AND filterAccessID = 'A3'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'order/link'],
                "posAccessID = 'A' AND filterAccessID = 'A6'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'order/cancel-table'],
                "posAccessID = 'A' AND filterAccessID = 'A7'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'payment,setting/get-payment-method,payment/view,payment/create,payment/view-for-edit,payment/print,payment/reprint,payment/print-edited,payment/print-void'],
                "posAccessID = 'A' AND filterAccessID = 'A9'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'promo,promo/apply-bill,promo/apply-order-head,promo/apply-order-menu'],
                "posAccessID = 'A' AND filterAccessID = 'A10'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'sales,sales/view'],
                "posAccessID = 'B' AND filterAccessID = 'B1'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'sales/void'],
                "posAccessID = 'B' AND filterAccessID = 'B5'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'member,member/view'],
                "posAccessID = 'C' AND filterAccessID = 'C1'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'member/create'],
                "posAccessID = 'C' AND filterAccessID = 'C2'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'member/update'],
                "posAccessID = 'C' AND filterAccessID = 'C3'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'deposit'],
                "posAccessID = 'D' AND filterAccessID = 'D1'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'deposit/create,deposit/print'],
                "posAccessID = 'D' AND filterAccessID = 'D2'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'withdrawal'],
                "posAccessID = 'E' AND filterAccessID = 'E1'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'withdrawal/create,withdrawal/print'],
                "posAccessID = 'E' AND filterAccessID = 'E2'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'shift,shift/current'],
                "posAccessID = 'F' AND filterAccessID = 'F1'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'shift/in'],
                "posAccessID = 'F' AND filterAccessID = 'F2'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'shift/end,shift/print-end'],
                "posAccessID = 'F' AND filterAccessID = 'F3'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'shift/out'],
                "posAccessID = 'F' AND filterAccessID = 'F4'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'shift/print-out'],
                "posAccessID = 'G' AND filterAccessID = 'G1'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'shift/view'],
                "posAccessID = 'G' AND filterAccessID = 'G2'");

            $this->update(PosFilterAccess::tableName(), ['action' => 'station/save'],
                "posAccessID = 'I' AND filterAccessID = 'I2'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'setting,setting/local,setting/local-setting,setting/get-cancel-reason,setting/get-cash-denom,setting/get-gender,setting/get-notes,setting/get-payment-method,setting/get-pos-data,setting/get-printer-connection,setting/get-printer-type,setting/get-station,setting/get-visit-purpose,setting/unsubscribe-to-topic,setting/save,setting/save-shift-out-printing-settings,setting/get-dual-display-image,pos-update,pos-update/check-version,pos-update/apply-update'],
                "posAccessID = 'J' AND filterAccessID = 'J1'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'setting/test-print'],
                "posAccessID = 'J' AND filterAccessID = 'J2'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'setting/open-drawer'],
                "posAccessID = 'J' AND filterAccessID = 'J3'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'sync,sync/push,sync/fetch,install,install/get-branch,install/run,migrate,migrate/get-branch,migrate/run'],
                "posAccessID = 'J' AND filterAccessID = 'J4'");

            $this->update(PosFilterAccess::tableName(),
                ['action' => 'branch-menu/save'],
                "posAccessID = 'H' AND filterAccessID = 'H2'");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PosFilterAccess::tableName(), true)->getColumn('action') !== null) {
            $this->dropColumn(PosFilterAccess::tableName(), 'action');
        }
    }

}
