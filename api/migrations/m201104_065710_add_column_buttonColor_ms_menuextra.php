<?php

use app\models\MenuExtra;
use yii\db\Migration;

/**
 * Class m201104_065710_add_column_buttonColor_ms_menuextra
 */
class m201104_065710_add_column_buttonColor_ms_menuextra extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('buttonColor') === null) {
            $this->addColumn(MenuExtra::tableName(), 'buttonColor',
                $this->string(50)->after('notes')->defaultValue(NULL));
        }
        
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('buttonColor') !== null) {
            $this->dropColumn(MenuExtra::tableName(), 'buttonColor');
        }
    }
}
