<?php

use app\models\Brand;
use yii\db\Migration;

/**
 * Class m200406_090001_create_ms_brand
 */
class m200406_090001_create_ms_brand extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(Brand::tableName(), true) === null) {
            $this->createTable(Brand::tableName(),
                [
                    
                    'brandID' => $this->primaryKey(),
                    'brandName' => $this->string(50)->notNull(),
                    'posXenditApiKey' => $this->string(100)->null(),
                    'posXenditVerificationToken' => $this->string(100)->null(),
                    'posMidtransServerKey' => $this->string(100)->null(),
                    'ezoXenditApiKey' => $this->string(100)->null(),
                    'ezoXenditVerificationToken' => $this->string(100)->null(),
                    'ezoMidtransServerKey' => $this->string(100)->null(),
                    'flagActive' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()->defaultValue('0'),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Brand::tableName(), true) !== null) {
            $this->dropTable(Brand::tableName());
        }
    }
}
