<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m210527_074328_update_lk_posfilteraccess
 */
class m210527_074328_update_lk_posfilteraccess extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (PosFilterAccess::find()->where(['posAccessID' => 'A', 'filterAccessID' => 'A1'])->exists()) {
             $this->update(PosFilterAccess::tableName(),
                 ['subNodes' => '/take-away,/take-away-classic,/booking-list'],
                 "posAccessID = 'A' AND filterAccessID = 'A1'");
         }
     }
 
     public function down()
     {
         $this->delete('lk_posfilteraccess', 'filterAccessID = "A1"');
     }
}
