<?php

use app\models\SalesInfo;
use yii\db\Migration;

/**
 * Class m211221_031727_alter_tr_salesinfo_value
 */
class m211221_031727_alter_tr_salesinfo_value extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesInfo::tableName(), true)->getColumn('value') !== null) {
            $this->alterColumn(SalesInfo::tableName(), 'value', $this->string(500)->append('CHARACTER SET utf8 COLLATE utf8_unicode_ci'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesInfo::tableName(), true)->getColumn('value') !== null) {
            $this->alterColumn(SalesInfo::tableName(), 'value', $this->string(500));
        }
    }
}
