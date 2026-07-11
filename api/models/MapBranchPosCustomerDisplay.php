<?php

namespace app\models;

use Yii;
use yii\data\ActiveDataProvider;
use app\components\AppHelper;

/**
 * This is the model class for table "map_user_branch".
 *
 * @property int $branchID
 * @property int $posCustomerDisplayID
 */
class MapBranchPosCustomerDisplay extends \yii\db\ActiveRecord {

    public static function tableName() {
        return 'map_branchposcustomerdisplay';
    }

    public function rules() {
        return [
            [['branchID', 'posCustomerDisplayID'], 'required'],
            [['branchID', 'posCustomerDisplayID'], 'integer'],
            [['branchID', 'posCustomerDisplayID'], 'safe']
        ];
    }

}
