<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m200803_065452_update_soldoutlimitinfo_lk_posfilteraccess
 */
class m200803_065452_update_soldoutlimitinfo_lk_posfilteraccess extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
       if (PosFilterAccess::find()->where(['posAccessID' => 'H', 'filterAccessID' => 'H1'])->exists()) {
            $this->update(PosFilterAccess::tableName(),
                ['subNodes' => '/sold-out-limit-info'],
                "posAccessID = 'H' AND filterAccessID = 'H1'");
        }
    }

    public function down()
    {
        $this->delete('lk_posfilteraccess', 'filterAccessID = "H1"');
    }
}