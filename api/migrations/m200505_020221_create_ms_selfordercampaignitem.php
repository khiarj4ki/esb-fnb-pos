<?php

use app\models\MsSelfOrderCampaignItem;
use yii\db\Migration;

/**
 * Class m200505_020221_create_ms_selfordercampaignitem
 */
class m200505_020221_create_ms_selfordercampaignitem extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignItem::tableName(), true) === null) {
            $this->createTable(MsSelfOrderCampaignItem::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'selfOrderCampaignID' => $this->integer()->notNull(),
                    'itemType' => $this->string(20)->defaultValue(NULL),
                    'itemQty' => $this->decimal(20,4)->defaultValue(NULL),
                    'itemMenuID' => $this->integer()->defaultValue(NULL),
                    'itemDiscountVal' => $this->decimal(20,4)->defaultValue(NULL),
                    'itemText' => $this->string(200)->defaultValue(NULL),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignItem::tableName(), true) !== null) {
            $this->dropTable(MsSelfOrderCampaignItem::tableName());
        }
    }
}
