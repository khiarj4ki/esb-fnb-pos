<?php

use app\models\DepositWithdrawalHead;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m231218_091452_add_indexing_member_code
 */
class m231218_091452_add_indexing_member_code extends Migration
{
    public function up()
    {
        $checkMemberCodeMsMember = "SHOW INDEX FROM " . Member::tableName() . " WHERE Key_name = 'idx_ms_member_memberCode'";
        if (!$this->db->createCommand($checkMemberCodeMsMember)->queryScalar()) {
            $this->createIndex('idx_ms_member_memberCode', Member::tableName(), 'memberCode');
        }

        $checkMemberCodeTrMemberDepositWithDrawlHead = "SHOW INDEX FROM " . DepositWithdrawalHead::tableName() . " WHERE Key_name = 'idx_tr_depositWithDrawlHead_memberCode'";
        if (!$this->db->createCommand($checkMemberCodeTrMemberDepositWithDrawlHead)->queryScalar()) {
            $this->createIndex('idx_tr_depositWithDrawlHead_memberCode', DepositWithdrawalHead::tableName(), 'memberCode');
        }

        $checkMemberCodeTrMemberDeposit = "SHOW INDEX FROM " . MemberDeposit::tableName() . " WHERE Key_name = 'idx_tr_memberDeposit_memberCode'";
        if (!$this->db->createCommand($checkMemberCodeTrMemberDeposit)->queryScalar()) {
            $this->createIndex('idx_tr_memberDeposit_memberCode', MemberDeposit::tableName(), 'memberCode');
        }

        $checkMemberCodeTrSalesHead = "SHOW INDEX FROM " . SalesHead::tableName() . " WHERE Key_name = 'idx_tr_salesHead_memberCode'";
        if (!$this->db->createCommand($checkMemberCodeTrSalesHead)->queryScalar()) {
            $this->createIndex('idx_tr_salesHead_memberCode', SalesHead::tableName(), 'memberCode');
        }
    }

    public function down()
    {
        $checkMemberCodeMsMember = "SHOW INDEX FROM " . Member::tableName() . " WHERE Key_name = 'idx_ms_member_memberCode'";
        if ($this->db->createCommand($checkMemberCodeMsMember)->queryScalar()) {
            $this->dropIndex('idx_ms_member_memberCode', Member::tableName());
        }

        $checkMemberCodeTrMemberDepositWithDrawlHead = "SHOW INDEX FROM " . DepositWithdrawalHead::tableName() . " WHERE Key_name = 'idx_tr_depositWithDrawlHead_memberCode'";
        if ($this->db->createCommand($checkMemberCodeTrMemberDepositWithDrawlHead)->queryScalar()) {
            $this->createIndex('idx_tr_depositWithDrawlHead_memberCode', DepositWithdrawalHead::tableName(), 'memberCode');
        }

        $checkMemberCodeTrMemberDeposit = "SHOW INDEX FROM " . MemberDeposit::tableName() . " WHERE Key_name = 'idx_tr_memberDeposit_memberCode'";
        if ($this->db->createCommand($checkMemberCodeTrMemberDeposit)->queryScalar()) {
            $this->createIndex('idx_tr_memberDeposit_memberCode', MemberDeposit::tableName(), 'memberCode');
        }

        $checkMemberCodeTrSalesHead = "SHOW INDEX FROM " . SalesHead::tableName() . " WHERE Key_name = 'idx_tr_salesHead_memberCode'";
        if ($this->db->createCommand($checkMemberCodeTrSalesHead)->queryScalar()) {
            $this->createIndex('idx_tr_salesHead_memberCode', SalesHead::tableName(), 'memberCode');
        }
    }
}
