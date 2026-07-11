<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m201118_080732_add_column_flagAuthorization_ms_promotionhead
 */
class m201118_080732_add_column_flagAuthorization_ms_promotionhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagAuthorization') === null) {
            $this->addColumn(PromotionHead::tableName(), 'flagAuthorization',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue(0)->after('flagMenuExtra'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagAuthorization') !== null) {
            $this->dropColumn(PromotionHead::tableName(),
                'flagAuthorization');
        }
    }
}
