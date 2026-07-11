<?php
use app\components\AppHelper;
use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m200729_042325_add_index_ms_menutemplatedetail
 */
class m200729_042325_add_index_ms_menutemplatedetail extends Migration {
    /**
     * @inheritdoc 
     */
    public function up() {
        $dbFile = require(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config/db.php');
        $mainDbName = AppHelper::getDsnAttribute('dbname', $dbFile['dsn']);

        $checkIndexMenuTemplateID = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
            MenuTemplateDetail::tableName() . "' " .
            "AND INDEX_NAME = 'idx_menuTemplateID_ms_menutemplatedetail' ";
        if (!$this->db->createCommand($checkIndexMenuTemplateID)->queryScalar()) {
            $this->createIndex('idx_menuTemplateID_ms_menutemplatedetail',
                MenuTemplateDetail::tableName(), 'menuTemplateID');
        }

        $checkIndexMenuID = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
            MenuTemplateDetail::tableName() . "' " .
            "AND INDEX_NAME = 'idx_menuID_ms_menutemplatedetail' ";
        if (!$this->db->createCommand($checkIndexMenuID)->queryScalar()) {
            $this->createIndex('idx_menuID_ms_menutemplatedetail',
                MenuTemplateDetail::tableName(), 'menuID');
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $dbFile = require(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config/db.php');
        $mainDbName = AppHelper::getDsnAttribute('dbname', $dbFile['dsn']);

        $checkIndexMenuTemplateID = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
            MenuTemplateDetail::tableName() . "' " .
            "AND INDEX_NAME = 'idx_menuTemplateID_ms_menutemplatedetail' ";
        if ($this->db->createCommand($checkIndexMenuTemplateID)->queryScalar()) {
            $this->dropIndex('idx_menuTemplateID_ms_menutemplatedetail',
                MenuTemplateDetail::tableName());
        }

        $checkIndexMenuID = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
            MenuTemplateDetail::tableName() . "' " .
            "AND INDEX_NAME = 'idx_menuID_ms_menutemplatedetail' ";
        if ($this->db->createCommand($checkIndexMenuID)->queryScalar()) {
            $this->dropIndex('idx_menuID_ms_menutemplatedetail',
                MenuTemplateDetail::tableName());
        }
    }

}
