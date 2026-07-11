<?php
use app\models\BranchMenu;
use app\models\Menu;
use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m191019_103605_create_ms_menutemplatedetail
 */
class m191019_103605_create_ms_menutemplatedetail extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true) === null) {
            $this->createTable(MenuTemplateDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'menuTemplateID' => $this->string(50),
                    'menuID' => $this->integer(),
                    'beforePrice' => $this->decimal(20, 4),
                    'price' => $this->decimal(20, 4),
                    'flagActive' => $this->tinyInteger(1),
            ]);

            $this->execute('INSERT INTO ' . MenuTemplateDetail::tableName() . ' ' .
                'SELECT NULL, a.branchID, a.menuID, 0, b.price, a.flagActive ' .
                'FROM ' . BranchMenu::tableName() . ' a JOIN ' . Menu::tableName() . ' b ON a.menuID = b.menuID');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true) !== null) {
            $this->dropTable(MenuTemplateDetail::tableName());
        }
    }

}
