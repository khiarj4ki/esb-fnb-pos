<?php

use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m241028_100013_add_field_ms_menutemplatedetail_starttTime_endTime
 */
class m241028_100013_add_field_ms_menutemplatedetail_starttTime_endTime extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('startTime') === null) {
            $this->addColumn(MenuTemplateDetail::tableName(), 'startTime',
                $this->time()->after('flagShowEZO'));
        }
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('endTime') === null) {
            $this->addColumn(MenuTemplateDetail::tableName(), 'endTime',
                $this->time()->after('startTime'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('startTime') !== null) {
            $this->dropColumn(MenuTemplateDetail::tableName(),
                'startTime');
        }
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('endTime') !== null) {
            $this->dropColumn(MenuTemplateDetail::tableName(),
                'endTime');
        }
    }
}
