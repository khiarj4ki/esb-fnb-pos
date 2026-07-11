<?php
use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m191019_103608_add_flag_member_ms_promotionhead
 */
class m191019_103608_add_flag_member_ms_promotionhead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagMemberOnly') === null) {
            $this->addColumn(PromotionHead::tableName(), 'flagMemberOnly',
                $this->tinyInteger(1)->defaultValue(0)->after('notes'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagMemberOnly') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'flagMemberOnly');
        }
    }

}
