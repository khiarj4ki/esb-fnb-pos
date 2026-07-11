<?php

use app\models\PromotionCategory;
use app\models\PromotionDay;
use app\models\PromotionDetail;
use app\models\PromotionPackageSub;
use app\models\PromotionTime;
use yii\db\Migration;

/**
 * Class m231107_085541_add_indexing_promotion_table
 */
class m231107_085541_add_indexing_promotion_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $checkIndexPromotionDay = "SHOW INDEX FROM " . PromotionDay::tableName() . " WHERE Key_name = 'idx_ms_promotionday_promotionID'";
        if (!$this->db->createCommand($checkIndexPromotionDay)->queryScalar())
        {
            $this->createIndex('idx_ms_promotionday_promotionID', PromotionDay::tableName(), 'promotionID');
        }

        $checkIndexPromotionTime = "SHOW INDEX FROM " . PromotionTime::tableName() . " WHERE Key_name = 'idx_ms_promotiontime_promotionID'";
        if (!$this->db->createCommand($checkIndexPromotionTime)->queryScalar())
        {
            $this->createIndex('idx_ms_promotiontime_promotionID', PromotionTime::tableName(), 'promotionID');
        }

        $checkIndexPromotionCategory = "SHOW INDEX FROM " . PromotionCategory::tableName() . " WHERE Key_name = 'idx_ms_promotioncategory_promotionID'";
        if (!$this->db->createCommand($checkIndexPromotionCategory)->queryScalar())
        {
            $this->createIndex('idx_ms_promotioncategory_promotionID', PromotionCategory::tableName(), 'promotionID');
        }

        $checkIndexPromotionDetail = "SHOW INDEX FROM " . PromotionDetail::tableName() . " WHERE Key_name = 'idx_ms_promotiondetail_promotionID'";
        if (!$this->db->createCommand($checkIndexPromotionDetail)->queryScalar())
        {
            $this->createIndex('idx_ms_promotiondetail_promotionID', PromotionDetail::tableName(), 'promotionID');
        }

        $checkIndexPromotionPackageSub = "SHOW INDEX FROM " . PromotionPackageSub::tableName() . " WHERE Key_name = 'idx_ms_promotionpackagesub_promotionID'";
        if (!$this->db->createCommand($checkIndexPromotionPackageSub)->queryScalar())
        {
            $this->createIndex('idx_ms_promotionpackagesub_promotionID', PromotionPackageSub::tableName(), 'promotionID');
        }

    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkIndexPromotionPackageSub = "SHOW INDEX FROM " . PromotionPackageSub::tableName() . " WHERE Key_name = 'idx_ms_promotionpackagesub_promotionID'";
        if ($this->db->createCommand($checkIndexPromotionPackageSub)->queryScalar())
        {
            $this->dropIndex('idx_ms_promotionpackagesub_promotionID', PromotionPackageSub::tableName());
        }

        $checkIndexPromotionDetail = "SHOW INDEX FROM " . PromotionDetail::tableName() . " WHERE Key_name = 'idx_ms_promotiondetail_promotionID'";
        if ($this->db->createCommand($checkIndexPromotionDetail)->queryScalar())
        {
            $this->dropIndex('idx_ms_promotiondetail_promotionID', PromotionDetail::tableName());
        }

        $checkIndexPromotionDay = "SHOW INDEX FROM " . PromotionDay::tableName() . " WHERE Key_name = 'idx_ms_promotionday_promotionID'";
        if ($this->db->createCommand($checkIndexPromotionDay)->queryScalar())
        {
            $this->dropIndex('idx_ms_promotionday_promotionID', PromotionDay::tableName());
        }

        $checkIndexPromotionTime = "SHOW INDEX FROM " . PromotionTime::tableName() . " WHERE Key_name = 'idx_ms_promotiontime_promotionID'";
        if ($this->db->createCommand($checkIndexPromotionTime)->queryScalar())
        {
            $this->dropIndex('idx_ms_promotiontime_promotionID', PromotionTime::tableName());
        }

        $checkIndexPromotionCategory = "SHOW INDEX FROM " . PromotionCategory::tableName() . " WHERE Key_name = 'idx_ms_promotioncategory_promotionID'";
        if ($this->db->createCommand($checkIndexPromotionCategory)->queryScalar())
        {
            $this->dropIndex('idx_ms_promotioncategory_promotionID', PromotionCategory::tableName());
        }
    }
}
