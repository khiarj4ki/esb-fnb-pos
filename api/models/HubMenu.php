<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Description of HubMenu
 *
 * @author USERESB06
 */
class HubMenu extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'hub_menu';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['ID', 'hubID', 'menuID', 'sourceMenuID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */

}
