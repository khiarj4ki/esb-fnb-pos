<?php

use app\models\BrandApiContent;
use yii\db\Migration;

/**
 * Class m201123_074238_alter_table_brandcontent
 */
class m201123_074238_alter_table_brandcontent extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function init() {
        $this->db = 'db';
        parent::init();
    }

    public function up() {
        if ($this->db->getTableSchema(BrandApiContent::tableName(), true)->getColumn('valueAttribute') !== null) {
            $this->alterColumn(BrandApiContent::tableName(), 'valueAttribute', $this->string(400));
        }
    }

    public function down() {
        if ($this->db->getTableSchema(BrandApiContent::tableName(), true)->getColumn('valueAttribute') !== null) {
            $this->alterColumn(BrandApiContent::tableName(), 'valueAttribute', $this->string(200));
        }
    }

}
