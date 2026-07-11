<?php

namespace app\models;
use yii\db\ActiveRecord;
use app\models\MsNotificationDetail;
use Yii;
use Exception;
use yii\db\Query;

/**
 * This is the model class for table "ms_notificationhead".
 *
 * @property int $notification
 * @property int $startDate
 * @property string $endDate
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class MsNotificationHead extends ActiveRecord {

    public static function tableName() {
        return 'ms_notificationhead';
    }

    public function rules() {
        return [
            [['notificationID', 'notificationTitle', 'notificationText', 'startDate', 'endDate', 'createdBy', 'createdDate', 'editedBy', 'editedDate'], 'required'],
            [['notificationID', 'notificationTitle', 'notificationText', 'startDate', 'endDate', 'createdBy', 'createdDate', 'editedBy', 'editedDate'], 'safe']
        ];
    }

    public function getNotificationDetail() {
        return $this->hasMany(MsNotificationDetail::className(),
                ['notificationID' => 'notificationID']);
    }

    public static function fetchLatestPosNotification() {
        $now = date('Y-m-d H:i:s');
        $branchID = Setting::getCurrentBranch();
        $subQueryNotificationDetail = (new Query)
            ->select(['notificationID'])
            ->from(MsNotificationDetail::tableName())
            ->where(['branchID' => $branchID])
            ->groupBy('notificationID')
            ->all();

        return MsNotificationHead::find()
            ->where(['IN', 'notificationID', $subQueryNotificationDetail])
            ->andWhere("'$now' BETWEEN startDate AND endDate")
            ->orderBy('notificationID DESC')
            ->one();
    }

}
