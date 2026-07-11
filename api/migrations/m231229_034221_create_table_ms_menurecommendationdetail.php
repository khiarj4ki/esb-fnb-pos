<?php

use app\models\MenuRecommendationDetail;
use yii\db\Migration;

/**
 * Class m231229_034221_create_table_ms_menurecommendationdetail
 */
class m231229_034221_create_table_ms_menurecommendationdetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuRecommendationDetail::tableName(), true) === null) {
            $this->createTable(MenuRecommendationDetail::tableName(), [
                'ID' => $this->primaryKey(),
                'menuRecommendationID' => $this->integer(11)->notNull(),
                'menuRecommendationGroupID' => $this->integer(11)->notNull(),
                'menuID' => $this->integer(11)->notNull(),
                'flagActive' => $this->tinyInteger(1)->notNull(),
                'orderID' => $this->integer(11)->notNull(),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuRecommendationDetail::tableName(), true) !== null) {
            $this->dropTable(MenuRecommendationDetail::tableName());
        }
    }
}
