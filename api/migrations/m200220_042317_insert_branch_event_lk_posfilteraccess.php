<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m200220_042317_insert_branch_event_lk_posfilteraccess
 */
class m200220_042317_insert_branch_event_lk_posfilteraccess extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!PosFilterAccess::find()->where(['posAccessID' => 'J', 'filterAccessID' => 'J6'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                [
                    'posAccessID' => 'J', 
                    'filterAccessID' => 'J6',
                    'description' => 'Branch Event List',
                    'subNodes' => '',
                    'action' => NULL,
                    'orderID' => '6'
                ]);
        }
    }

    public function down()
    {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "J6"');
    }
}
