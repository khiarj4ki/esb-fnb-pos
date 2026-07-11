<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m201007_091602_insert_access_printbill
 */
class m201007_091602_insert_access_printbill extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (!PosFilterAccess::find()->where(['posAccessID' => 'A', 'filterAccessID' => 'A14'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                [
                    'posAccessID' => 'A', 
                    'filterAccessID' => 'A14',
                    'description' => 'Print Bill',
                    'subNodes' => '',
                    'orderID' => '14'
                ]);
        }
    }

    public function down()
    {
        $this->delete(PosFilterAccess::tableName(), 'filterAccessID = "A14"');
    }
}
