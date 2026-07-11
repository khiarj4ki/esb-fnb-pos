<?php

namespace app\models;

use Yii;
use yii\data\ActiveDataProvider;
use app\components\AppHelper;

/**
 * This is the model class for table "map_user_branch".
 *
 * @property int $posCustomerDisplayID
 * @property string $posCustomerDisplayName
 * @property int $flagActive
 */
class MsPosCustomerDisplayHead extends \yii\db\ActiveRecord {

    public static function tableName() {
        return 'ms_poscustomerdisplayhead';
    }

    public function rules() {
        return [
            [['posCustomerDisplayName', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['posCustomerDisplayName'], 'string', 'max' => 50],
            [['imageFileUpload'], 'file','maxFiles' => 6],
            [['branchID', 'posCustomerDisplayName', 'flagActive', 'posCustomerDisplayID'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'posCustomerDisplayName' => Yii::t('app', 'Customer Display Name'),
            'branchID' => Yii::t('app', 'Branch')
        ];
    }

}
