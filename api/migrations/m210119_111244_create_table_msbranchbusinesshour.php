<?php

use app\models\MsBranchBusinessHour;
use yii\db\Migration;

/**
 * Class m210119_111244_create_table_msbranchbusinesshour
 */
class m210119_111244_create_table_msbranchbusinesshour extends Migration
{
     /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MsBranchBusinessHour::tableName(), true) === null) {
             $this->createTable(MsBranchBusinessHour::tableName(),
                 [
                    'branchID' => $this->integer()->notNull(),
                    'dayID' => $this->integer()->notNull(),
                    'startTime' => $this->time(),
                    'endTime' => $this->time()
             ]);
             
             $this->addPrimaryKey('PRIMARYKEY', 'ms_branchbusinesshour',
                ['branchID', 'dayID']);
             
         }
     }
 
     /**
      * @inheritdoc
      */
     public function down() {
         if ($this->db->getTableSchema(MsBranchBusinessHour::tableName(),
                 true) !== null) {
             $this->dropTable(MsBranchBusinessHour::tableName());
         }
     }
}
