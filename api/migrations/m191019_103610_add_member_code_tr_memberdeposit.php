<?php
use app\models\Member;
use app\models\MemberDeposit;
use yii\db\Migration;

/**
 * Class m191019_103610_add_member_code_tr_memberdeposit
 */
class m191019_103610_add_member_code_tr_memberdeposit extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MemberDeposit::tableName(), true)->getColumn('memberCode') === null) {
            $this->addColumn(MemberDeposit::tableName(), 'memberCode',
                $this->string(20)->after('memberID'));

            $this->execute('UPDATE ' . MemberDeposit::tableName() . ' a JOIN ' . Member::tableName() . ' b ' .
                'ON a.memberID = b.memberID SET a.memberCode = b.memberCode');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MemberDeposit::tableName(), true)->getColumn('memberCode') !== null) {
            $this->dropColumn(MemberDeposit::tableName(), 'memberCode');
        }
    }

}
