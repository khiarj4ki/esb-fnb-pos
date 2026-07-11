<?php
use app\models\DepositWithdrawalHead;
use app\models\Member;
use yii\db\Migration;

/**
 * Class m191019_103609_add_member_code_tr_depositwithdrawalhead
 */
class m191019_103609_add_member_code_tr_depositwithdrawalhead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(DepositWithdrawalHead::tableName(), true)->getColumn('memberCode') === null) {
            $this->addColumn(DepositWithdrawalHead::tableName(), 'memberCode',
                $this->string(20)->after('memberID'));

            $this->execute('UPDATE ' . DepositWithdrawalHead::tableName() . ' a JOIN ' . Member::tableName() . ' b ' .
                'ON a.memberID = b.memberID SET a.memberCode = b.memberCode');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(DepositWithdrawalHead::tableName(), true)->getColumn('memberCode') !== null) {
            $this->dropColumn(DepositWithdrawalHead::tableName(), 'memberCode');
        }
    }

}
