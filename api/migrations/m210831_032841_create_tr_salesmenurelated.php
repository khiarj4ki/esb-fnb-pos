<?php

use app\models\SalesMenuRelated;
use yii\db\Migration;

/**
 * Class m210831_032841_create_tr_salesmenurelated
 */
class m210831_032841_create_tr_salesmenurelated extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenuRelated::tableName(), true) === null) {
            $this->createTable(
                SalesMenuRelated::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'salesNum' => $this->string(50)->notNull(),
                    'salesMenuID' => $this->integer()->notNull(),
                    'mainMenuID' => $this->integer(),
                    'relatedMenuID' => $this->integer()->notNull()
                ]
            );

            $this->createIndex(
                'salesNum_INDEX',
                SalesMenuRelated::tableName(),
                'salesNum'
            );
            $this->createIndex(
                'salesMenuID_INDEX',
                SalesMenuRelated::tableName(),
                'salesMenuID'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesMenuRelated::tableName(), true) !== null) {
            $this->dropTable(SalesMenuRelated::tableName());
        }
    }
}
