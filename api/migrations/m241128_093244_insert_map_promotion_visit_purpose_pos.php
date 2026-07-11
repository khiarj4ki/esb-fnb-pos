<?php

use app\models\MapPromotionVisitPurpose;
use yii\db\Migration;

/**
 * Class m241128_093244_insert_map_promotion_visit_purpose_pos
 */
class m241128_093244_insert_map_promotion_visit_purpose_pos extends Migration
{
/**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapPromotionVisitPurpose::tableName(), true) === null) {
            $this->createTable(MapPromotionVisitPurpose::tableName(),
            [
                'ID' => $this->integer(11)->notNull()->append('AUTO_INCREMENT PRIMARY KEY'),
                'promotionID' => $this->integer(11)->null(),
                'visitPurposeID' => $this->integer(11)->null(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(MapPromotionVisitPurpose::tableName(), true) !== null) {
            $this->dropTable(MapPromotionVisitPurpose::tableName());
        }
    }

}
