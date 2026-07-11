<?php

use app\models\PosFilterAccess;
use app\models\PosUserAccess;
use yii\db\Migration;

/**
 * Class m210711_041512_insert_lk_posfilteraccess_edit_menu_station
 */
class m210711_041512_insert_lk_posfilteraccess_edit_menu_station extends Migration
{

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!PosFilterAccess::find()->where(['posAccessID' => 'H', 'filterAccessID' => 'H3'])->exists()) {
            $this->insert(
                PosFilterAccess::tableName(),
                [
                    'posAccessID' => 'H',
                    'filterAccessID' => 'H3',
                    'description' => 'Edit Menu Station',
                    'subNodes' => '',
                    'orderID' => '3'
                ]
            );

            if (!PosUserAccess::find()->where(['filterAccessID' => 'H3'])->exists()) {
                $this->execute("INSERT INTO " . PosUserAccess::tableName() . " (posUserRoleID,filterAccessID,hasAccess) " .
                    "SELECT posUserRoleID, 'H3', hasAccess " .
                    "FROM " . PosUserAccess::tableName() . " where filterAccessID = 'H2'");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if (PosFilterAccess::find()->where(['posAccessID' => 'H', 'filterAccessID' => 'H3'])->exists()) {
            PosFilterAccess::deleteAll(['posAccessID' => 'H', 'filterAccessID' => 'H3']);
            $this->delete(
                PosUserAccess::tableName(),
                ['filterAccessID' => 'H3']
            );
        }
    }
}
