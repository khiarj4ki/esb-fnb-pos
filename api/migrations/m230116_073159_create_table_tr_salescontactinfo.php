<?php

use app\models\SalesContactInfo;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m230116_073159_create_table_tr_salescontactinfo
 */
class m230116_073159_create_table_tr_salescontactinfo extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesContactInfo::tableName(), true) === null) {
            $this->createTable(SalesContactInfo::tableName(), [
                'salesContactInfoID' => Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                'salesNum' => $this->string(20)->notNull(),
                'customerPhoneNum' => $this->string(20)->notNull(),
            ]);

            $this->createIndex('idx_salescontactinfo_salesNum', SalesContactInfo::tableName(), 'salesNum');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesContactInfo::tableName(), true) !== null) {
            $this->dropTable(SalesContactInfo::tableName());
        }
    }
}
