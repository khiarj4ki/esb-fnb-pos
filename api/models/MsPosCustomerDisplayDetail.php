<?php

namespace app\models;

use yii\helpers\Url;

/**
 * This is the model class for table "map_user_branch".
 *
 * @property int $posCustomerDisplayID
 * @property string $posCustomerDisplayName
 * @property int $flagActive
 */
class MsPosCustomerDisplayDetail extends \yii\db\ActiveRecord {
    
    public static function tableName() {
        return 'ms_poscustomerdisplaydetail';
    }

    public function rules() {
        return [
            [['posCustomerDisplayID', 'imageUrl'], 'required'],
            [['imageUrl'], 'string'],
            [['posCustomerDisplayID'], 'integer'],
            [['ID', 'imageFileUpload'], 'safe'],
        ];
    }

    public function attributeLabels() {
        return [
            'posCustomerDisplayName' => 'Customer Display Name'
        ];
    }

    public function getPosCustomerDisplayApplication() {
        return $this->hasMany(PosCustomerDisplayApplication::class, 
            ['posCustomerDetailID' => 'ID']
        );
    }

    public static function getCustomerDisplayImage($applicationID) {
        $dirCustomerDisplay = Url::to('@web/images/customer-display/', true);
        $models = MsPosCustomerDisplayDetail::find()
            ->select('*')
            ->innerJoinWith('posCustomerDisplayApplication')
            ->where(['applicationID' => $applicationID])
            ->all();

        $data = [];
        foreach ($models as $images) {
            $data[] = [
                "ID" => $images->ID,
                "posCustomerDisplayID" => $images->posCustomerDisplayID,
                "imageUrl" => $dirCustomerDisplay . '/' . $images->imageUrl
            ];
        }
        return $data;
    }

}
