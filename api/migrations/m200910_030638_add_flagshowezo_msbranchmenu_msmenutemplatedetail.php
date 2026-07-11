<?php

use yii\db\Migration;
use app\models\BranchMenu;
use app\models\MenuTemplateDetail;

/**
 * Class m200910_030638_add_flagshowezo_msbranchmenu_msmenutemplatedetail
 */
class m200910_030638_add_flagshowezo_msbranchmenu_msmenutemplatedetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(BranchMenu::tableName(), true)->getColumn('flagShowEzo') === null) {
            $this->addColumn(BranchMenu::tableName(), 'flagShowEzo',
                $this->integer(1)->after('flagSoldOut')->null());
        }

        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('flagShowEzo') === null) {
            $this->addColumn(MenuTemplateDetail::tableName(), 'flagShowEzo',
                $this->integer(1)->after('flagActive')->null());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(BranchMenu::tableName(), true)->getColumn('flagShowEzo') !== null) {
            $this->dropColumn(BranchMenu::tableName(), 'flagShowEzo');
        }

        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('flagShowEzo') !== null) {
            $this->dropColumn(MenuTemplateDetail::tableName(), 'flagShowEzo');
        }
    }
}
