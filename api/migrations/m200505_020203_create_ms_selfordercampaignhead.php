<?php

use app\models\MsSelfOrderCampaignHead;
use yii\db\Migration;

/**
 * Class m200505_020203_create_ms_selfordercampaignhead
 */
class m200505_020203_create_ms_selfordercampaignhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true) === null) {
            $this->createTable(MsSelfOrderCampaignHead::tableName(),
                [
                    'selfOrderCampaignID' => $this->primaryKey(),
                    'selfOrderCampaignName' => $this->string(100)->notNull(),
                    'selfOrderCampaignType' => $this->string(30)->notNull(),
                    'activeDateFrom' => $this->dateTime()->defaultValue(NULL),
                    'activeDateTo' => $this->dateTime()->defaultValue(NULL),
                    'effectType' => $this->string(20)->defaultValue(NULL),
                    'preAmountVal' => $this->decimal(20,4)->notNull(),
                    'preAmountMsg' => $this->string(100)->notNull(),
                    'minAmountVal' => $this->decimal(20,4)->notNull(),
                    'postAmountMsg' => $this->string(100)->notNull(),
                    'menuID' => $this->integer()->defaultValue(NULL),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'flagMultiple' => $this->tinyInteger(1)->notNull()->defaultValue(0),
                    'createdBy' => $this->string(50)->defaultValue(NULL),
                    'createdDate' => $this->dateTime()->defaultValue(NULL),
                    'editedBy' => $this->string(50)->defaultValue(NULL),
                    'editedDate' => $this->dateTime()->defaultValue(NULL),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true) !== null) {
            $this->dropTable(MsSelfOrderCampaignHead::tableName());
        }
    }
}
