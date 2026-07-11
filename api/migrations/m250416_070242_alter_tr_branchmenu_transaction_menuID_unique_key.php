<?php

use app\models\BranchMenuTransaction;
use yii\db\Migration;

/**
 * Class m250416_070242_alter_tr_branchmenu_transaction_menuID_unique_key
 */
class m250416_070242_alter_tr_branchmenu_transaction_menuID_unique_key extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $dbName = explode(';', Yii::$app->db->dsn);
        $dbName = explode('=', $dbName[1]);
        $dbName = $dbName[1];
        $checkQuery = "SHOW INDEX FROM " . BranchMenuTransaction::tableName() . " FROM " . $dbName . " WHERE Key_name = 'idx-branchmenu-transaction'";
        echo $checkQuery;
        if ($this->db->createCommand($checkQuery)->queryScalar()) {
            $this->dropIndex('idx-branchmenu-transaction', BranchMenuTransaction::tableName());
            $this->createIndex('idx-branchmenu-transaction', BranchMenuTransaction::tableName(), ['ID', 'menuID', 'salesNum', 'category', 'salesMenuID'], true);
        }

    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $dbName = explode(';', Yii::$app->db->dsn);
        $dbName = explode('=', $dbName[1]);
        $dbName = $dbName[1];
        $checkQuery = "SHOW INDEX FROM " . BranchMenuTransaction::tableName() . " FROM " . $dbName . " WHERE Key_name = 'idx-branchmenu-transaction'";
        if ($this->db->createCommand($checkQuery)->queryScalar()) {
            $this->dropIndex('idx-branchmenu-transaction', BranchMenuTransaction::tableName());
        }
    }
}
