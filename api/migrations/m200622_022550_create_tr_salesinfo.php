<?php

use app\models\SalesInfo;
use yii\db\Migration;

/**
 * Class m200622_022550_create_tr_salesinfo
 */
class m200622_022550_create_tr_salesinfo extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(SalesInfo::tableName(), true) === null) {
            
            $this->createTable(SalesInfo::tableName(),
                    [
                        'ID' => $this->primaryKey(),
                        'salesNum' => $this->string(20)->notNull(),                      
                        'key' => $this->string(100)->notNull(),
                        'value' => $this->string(500),
                    ]
            );
        }
    }

    public function down() {
        if ($this->db->getTableSchema(SalesInfo::tableName(), true) !== null) {
            $this->dropTable(SalesInfo::tableName());
        }
        return true;
    }
}
