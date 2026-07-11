<?php

use app\models\MenuRecommendationHead;
use yii\db\Migration;

/**
 * Class m231229_032439_create_table_ms_menurecommendationhead
 */
class m231229_032439_create_table_ms_menurecommendationhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuRecommendationHead::tableName(), true) === null) {
            $this->createTable(MenuRecommendationHead::tableName(), [
                'menuRecommendationID' => $this->primaryKey(),
                'menuTemplateID' => $this->integer(11)->notNull(),
                'flagActive' => $this->tinyInteger(1)->notNull(),
                'createdBy' => $this->string(50),
                'createdDate' => $this->dateTime(),
                'editedBy' => $this->string(50),
                'editedDate' => $this->dateTime(),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuRecommendationHead::tableName(), true) !== null) {
            $this->dropTable(MenuRecommendationHead::tableName());
        }
    }
}
