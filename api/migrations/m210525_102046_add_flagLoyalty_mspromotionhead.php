<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m210525_102046_add_flagLoyalty_mspromotionhead
 */
class m210525_102046_add_flagLoyalty_mspromotionhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagLoyalty') === null) {
            $this->addColumn(PromotionHead::tableName(), 'flagLoyalty', 
                $this->integer(1)->after('flagMemberOnly')->defaultValue(0)->notNull());
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagLoyalty') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'flagLoyalty');
        }
    }
}
