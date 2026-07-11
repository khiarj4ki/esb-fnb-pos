<?php
use app\models\Branch;
use app\models\BranchMenu;
use app\models\MenuTemplateHead;
use yii\db\Migration;

/**
 * Class m191019_103606_create_ms_menutemplatehead
 */
class m191019_103606_create_ms_menutemplatehead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateHead::tableName(), true) === null) {
            $this->createTable(MenuTemplateHead::tableName(),
                [
                    'menuTemplateID' => $this->primaryKey(),
                    'menuTemplateName' => $this->string(50),
                    'activeDate' => $this->date(),
                    'notes' => $this->string(1000),
                    'flagActive' => $this->tinyInteger(1),
                    'createdBy' => $this->string(50),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(50),
                    'editedDate' => $this->dateTime(),
            ]);

            $this->execute('INSERT INTO ' . MenuTemplateHead::tableName() . ' ' .
                "SELECT a.branchID, CONCAT('Template - ', b.branchName), NOW(), 'MIGRATION', 1, 'SYSTEM', NOW(), 'SYSTEM', NOW() " .
                'FROM ' . BranchMenu::tableName() . ' a JOIN ' . Branch::tableName() . ' b ON a.branchID = b.branchID ' .
                'GROUP BY b.branchID, b.branchName');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateHead::tableName(), true) !== null) {
            $this->dropTable(MenuTemplateHead::tableName());
        }
    }

}
