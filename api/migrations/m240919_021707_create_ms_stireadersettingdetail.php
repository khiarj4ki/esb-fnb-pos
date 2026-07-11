<?php

use app\models\MsReaderSettingTamanSafari;
use app\models\MsReaderSettingTamanSafariDetail;
use yii\db\Migration;

/**
 * Class m240919_021707_create_ms_stireadersettingdetail
 */
class m240919_021707_create_ms_stireadersettingdetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsReaderSettingTamanSafariDetail::tableName(), true) === null) {
            $this->createTable(MsReaderSettingTamanSafariDetail::tableName(), [
                'ID' => $this->primaryKey(),
                'headID' => $this->integer(50)->null(),
                'TID' => $this->string(10)->null()
            ]);

            $this->createIndex(
                'sti_INDEX',
                MsReaderSettingTamanSafariDetail::tableName(),
                ['ID','headID']
            );

        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsReaderSettingTamanSafariDetail::tableName(), true) !== null) {
            $this->dropTable(MsReaderSettingTamanSafariDetail::tableName());
        }
    } 

}
