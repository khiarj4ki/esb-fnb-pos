<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_cancelreason".
 *
 * @property int $cancelReasonID
 * @property string $cancelReasonDesc
 * @property int $cancelReasonTypeID
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class CancelReason extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_cancelreason';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['cancelReasonDesc', 'cancelReasonTypeID', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['flagActive'], 'integer'],
            [['cancelReasonID', 'createdDate', 'editedDate'], 'safe'],
            [['cancelReasonDesc'], 'string', 'max' => 50],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'cancelReasonID' => 'Cancel Reason ID',
            'cancelReasonDesc' => 'Cancel Reason Desc',
            'cancelReasonTypeID' => 'Cancel Reason Type',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public static function findActive() {
        return CancelReason::find()->andWhere([CancelReason::tableName() . '.flagActive' => 1])
                ->orderBy(CancelReason::tableName() . '.cancelReasonDesc');
    }

}
