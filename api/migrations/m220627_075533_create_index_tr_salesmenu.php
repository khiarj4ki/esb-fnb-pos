<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m220627_075533_create_index_tr_salesmenu
 */
class m220627_075533_create_index_tr_salesmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $chekQuery = "SHOW INDEX FROM " . SalesMenu::tableName() . " WHERE Key_name ='idx_tr_salesmenu_localID'";
        if (!$this->db->createCommand($chekQuery)->queryScalar()) {
            $this->createIndex('idx_tr_salesmenu_localID', SalesMenu::tableName(), 'localID');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $chekQuery = "SHOW INDEX FROM " . SalesMenu::tableName() . " WHERE Key_name = 'idx_tr_salesmenu_localID'";
        if ($this->db->createCommand($chekQuery)->queryScalar()) {
            $this->dropIndex('idx_tr_salesmenu_localID', SalesMenu::tableName());
        }
    }
}
