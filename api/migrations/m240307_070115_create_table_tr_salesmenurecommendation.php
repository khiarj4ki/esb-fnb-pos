<?php

use app\models\SalesMenuRecommendation;
use yii\db\Migration;

/**
 * Class m240307_070115_create_table_tr_salesmenurecommendation
 */
class m240307_070115_create_table_tr_salesmenurecommendation extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenuRecommendation::tableName(), true) === null) {
            $this->createTable(SalesMenuRecommendation::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'localID' => $this->integer(50),
                    'salesNum' => $this->string(50)->notNull(),
                    'salesMenuID' => $this->integer(11)->notNull()
                ]
            );

            $this->createIndex(
                'salesNum_INDEX',
                SalesMenuRecommendation::tableName(),
                'salesNum'
            );
            $this->createIndex(
                'salesMenuID_INDEX',
                SalesMenuRecommendation::tableName(),
                'salesMenuID'
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesMenuRecommendation::tableName(), true) !== null) {
            $this->dropTable(SalesMenuRecommendation::tableName());
        }
    }
}
