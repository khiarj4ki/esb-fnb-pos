<?php
use app\models\Member;
use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m191019_103612_add_member_code_tr_saleshead
 */
class m191019_103612_add_member_code_tr_saleshead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('memberCode') === null) {
            $this->addColumn(SalesHead::tableName(), 'memberCode',
                $this->string(20)->after('memberID'));

            $this->execute('UPDATE ' . SalesHead::tableName() . ' a JOIN ' . Member::tableName() . ' b ' .
                'ON a.memberID = b.memberID SET a.memberCode = b.memberCode');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('memberCode') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'memberCode');
        }
    }

}
