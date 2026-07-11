<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m200306_024934_insert_split_bill_access_control_posfilteraccess
 */
class m200306_024934_insert_split_bill_access_control_posfilteraccess extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (!PosFilterAccess::find()->where(['posAccessID' => 'A', 'filterAccessID' => 'A12'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                [
                    'posAccessID' => 'A', 
                    'filterAccessID' => 'A12',
                    'description' => 'Split Bill',
                    'subNodes' => '',
                    'orderID' => '12'
                ]);
        }
    }

    public function down()
    {
        $this->delete(PosFilterAccess::tableName(), 'filterAccessID = "A12"');
    }
}
