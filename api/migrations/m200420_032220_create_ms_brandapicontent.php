<?php

use app\models\BrandApiContent;
use yii\db\Migration;

/**
 * Class m200420_032220_create_ms_brandapicontent
 */
class m200420_032220_create_ms_brandapicontent extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(BrandApiContent::tableName(), true) === null) {
            $this->createTable(BrandApiContent::tableName(),
                [
                    
                    'brandID' => $this->integer()->notNull(),
                    'brandSettingID' => $this->integer()->notNull(),
                    'keyAttribute' => $this->string(200)->null(),
                    'valueAttribute' => $this->string(200)->null()
            ]);
            
            $this->addPrimaryKey('PRIMARY KEY', 
                BrandApiContent::tableName(), 
                ['brandID', 'brandSettingID', 'keyAttribute', 'valueAttribute']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(BrandApiContent::tableName(), true) !== null) {
            $this->dropTable(BrandApiContent::tableName());
        }
    }
}
