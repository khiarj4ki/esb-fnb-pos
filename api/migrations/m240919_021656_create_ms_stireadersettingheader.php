<?php

use app\models\MsReaderSettingTamanSafari;
use yii\db\Migration;

/**
 * Class m240919_021656_create_ms_stireadersettingheader
 */
class m240919_021656_create_ms_stireadersettingheader extends Migration
{
  /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsReaderSettingTamanSafari::tableName(), true) === null) {
            $this->createTable(MsReaderSettingTamanSafari::tableName(), [
                'ID' => $this->primaryKey(),
                'companyID' => $this->string(50)->null(),
                'companyCode' => $this->string(50)->null(),
                'companyName' => $this->string(100)->null(),
                'branchID' => $this->string(50)->null(),
                'branchCode' => $this->string(50)->null(),
                'branchName' => $this->string(100)->null(),
                'createdBy' => $this->string(100)->null(),
                'createdDate' => $this->dateTime()->null(),
                'editedBy' => $this->string(100)->null(),
                'editedDate' => $this->dateTime()->null(),
                'syncDate' => $this->dateTime()->null()
            ]);

            $this->createIndex(
                'sti_INDEX',
                MsReaderSettingTamanSafari::tableName(),
                ['ID','branchID','companyID']
            );

        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsReaderSettingTamanSafari::tableName(), true) !== null) {
            $this->dropTable(MsReaderSettingTamanSafari::tableName());
        }
    } 
}
