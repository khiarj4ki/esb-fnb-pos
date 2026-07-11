<?php

namespace app\models;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_notificationdetail".
 *
 * @property int $notificationID
 * @property int $branchID
 */
class MsNotificationDetail extends ActiveRecord {

    public static function tableName() {
        return 'ms_notificationdetail';
    }

    public function rules() {
        return [
            [['notificationDetailID', 'notificationID', 'branchID'], 'required']
        ];
    }

}
