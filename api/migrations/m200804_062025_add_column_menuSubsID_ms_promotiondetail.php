<?php

use app\models\PromotionDetail;
use yii\db\Migration;

/**
 * Class m200804_062025_add_column_menuSubsID_ms_promotiondetail
 */
class m200804_062025_add_column_menuSubsID_ms_promotiondetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionDetail::tableName(), true)->getColumn('menuSubsID') === null) {
            $this->addColumn(PromotionDetail::tableName(), 'menuSubsID',
                $this->integer(11)->after('qty'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PromotionDetail::tableName(), true)->getColumn('menuSubsID') !== null) {
            $this->dropColumn(PromotionDetail::tableName(), 'menuSubsID');
        }
    }

}
