<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m200226_051306_add_edit_remarks_lk_posfilteraccess
 */
class m200226_051306_add_edit_remarks_lk_posfilteraccess extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
       if (!PosFilterAccess::find()->where(['posAccessID' => 'B', 'filterAccessID' => 'B6'])->exists()) {
            $this->insert(PosFilterAccess::tableName(),
                [
                    'posAccessID' => 'B', 
                    'filterAccessID' => 'B6',
                    'description' => 'Edit Remarks',
                    'subNodes' => '',
                    'action' => 'sales/update-rermarks',
                    'orderID' => '6'
                ]);
        }
    }

    public function down()
    {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "B6"');
    }
}
