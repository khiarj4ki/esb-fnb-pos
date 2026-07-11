<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m200120_040323_alter_column_flag_menu_package_extra_ms_promotionhead
 */
class m200120_040323_alter_column_flag_menu_package_extra_ms_promotionhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagPackageContent') === null) {
            $this->addColumn(PromotionHead::tableName(),
                'flagPackageContent',
                $this->tinyInteger(1)->defaultValue(0)->after('flagMemberOnly'));
        }
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagMenuExtra') === null) {
            $this->addColumn(PromotionHead::tableName(),
                'flagMenuExtra',
                $this->tinyInteger(1)->defaultValue(0)->after('flagPackageContent'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagPackageContent') !== null) {
            $this->dropColumn(PromotionHead::tableName(),
                'flagPackageContent');
        }
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagMenuExtra') !== null) {
            $this->dropColumn(PromotionHead::tableName(),
                'flagMenuExtra');
        }
    }
}
