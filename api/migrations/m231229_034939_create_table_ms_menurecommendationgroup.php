<?php

use app\models\MenuRecommendationGroup;
use yii\db\Migration;

/**
 * Class m231229_034939_create_table_ms_menurecommendationgroup
 */
class m231229_034939_create_table_ms_menurecommendationgroup extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuRecommendationGroup::tableName(), true) === null) {
            $this->createTable(MenuRecommendationGroup::tableName(), [
                'menuRecommendationGroupID' => $this->primaryKey(),
                'menuRecommendationID' => $this->integer(11)->notNull(),
                'recommendationGroup' => $this->string(50),
                'orderID' => $this->integer(11)->notNull()
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuRecommendationGroup::tableName(), true) !== null) {
            $this->dropTable(MenuRecommendationGroup::tableName());
        }
    }
}
