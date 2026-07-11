<?php

use app\models\PosFilterAccess;
use yii\db\Migration;

/**
 * Class m210711_041454_update_lk_posfilteraccess_edit_branch_menu
 */
class m210711_041454_update_lk_posfilteraccess_edit_branch_menu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (PosFilterAccess::find()->where(['and', ['filterAccessID' => 'H2'], ['description' => 'Edit Branch menu']])->exists()) {
            $this->update(
                PosFilterAccess::tableName(),
                ['description' => 'Edit Qty & Sold Out'],
                ['filterAccessID' => 'H2', 'description' => 'Edit Branch menu']
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if (PosFilterAccess::find()->where(['and', ['filterAccessID' => 'H2'], ['description' => 'Edit Qty & Sold Out']])->exists()) {
            $this->update(
                PosFilterAccess::tableName(),
                ['description' => 'Edit Branch menu'],
                ['filterAccessID' => 'H2', 'description' => 'Edit Qty & Sold Out']
            );
        }
    }
}
