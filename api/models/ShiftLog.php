<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "tr_shiftlog".
 *
 * @property int $shiftID
 * @property int $branchID
 * @property string $shiftInTime
 * @property string $shiftOutTime
 * @property string $shiftInTotal
 * @property string $systemCashReceivedTotal
 * @property string $shiftOutTotal
 * @property string $shiftInUsername
 * @property string $shiftOutUsername
 * @property string $shiftOutNotes
 * @property string $syncDate
 * 
 * @property ShiftLogDetail[] $shiftLogDetails
 * @property Branch $branch
 * @property PosUser $shiftInUser
 * @property PosUser $shiftOutUser
 */
class ShiftLog extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_shiftlog';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['branchID', 'shiftInTime', 'shiftInTotal', 'shiftInUsername'], 'required'],
            [['branchID'], 'integer'],
            [['shiftID'], 'safe', 'on' => 'NEW_INSTALL'],
            [['shiftInTime', 'shiftOutTime', 'syncDate'], 'safe'],
            [['shiftInTotal', 'systemCashReceivedTotal', 'shiftOutTotal'], 'number'],
            [['shiftInUsername', 'shiftOutUsername'], 'string', 'max' => 50],
            [['shiftOutNotes'], 'string', 'max' => 200]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'shiftID' => 'Shift ID',
            'branchID' => 'Branch ID',
            'shiftInTime' => 'Shift In Time',
            'shiftOutTime' => 'Shift Out Time',
            'shiftInTotal' => 'Shift In Total',
            'systemCashReceivedTotal' => 'System Cash Received Total',
            'shiftOutTotal' => 'Shift Out Total',
            'shiftInUsername' => 'Shift In Username',
            'shiftOutUsername' => 'Shift Out Username',
            'shiftOutNotes' => 'Shift Out Notes',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['shiftInTime'] = function ($model) {
            return str_replace("-", "/", $model->shiftInTime);
        };
        $fields['shiftOutTime'] = function ($model) {
            return str_replace("-", "/", $model->shiftOutTime);
        };
        $fields['shiftInTotal'] = function ($model) {
            return (float) $model->shiftInTotal;
        };
        $fields['systemCashReceivedTotal'] = function ($model) {
            return (float) $model->systemCashReceivedTotal;
        };
        $fields['shiftOutTotal'] = function ($model) {
            return (float) $model->shiftOutTotal;
        };
        $fields['differenceTotal'] = function ($model) {
            if ($model->shiftOutTime) {
                return (float) $model->shiftInTotal + $model->systemCashReceivedTotal - $model->shiftOutTotal;
            }
            return 0;
        };
        $fields['branchName'] = function ($model) {
            return $model->branch->branchName;
        };
        $fields['shiftInUser'] = function ($model) {
            return $model->shiftInUser ? $model->shiftInUser->fullName : $model->shiftInUsername;
        };
        $fields['shiftOutUser'] = function ($model) {
            return $model->shiftOutUser ? $model->shiftOutUser->fullName : $model->shiftOutUsername;
        };

        return $fields;
    }

    public function getShiftLogDetails() {
        return $this->hasMany(ShiftLogDetail::class, ['shiftID' => 'shiftID'])
                ->with('shiftUser');
    }

    public function getBranch() {
        return $this->hasOne(Branch::class, ['branchID' => 'branchID']);
    }

    public function getShiftInUser() {
        return $this->hasOne(PosUser::class, ['username' => 'shiftInUsername']);
    }

    public function getShiftOutUser() {
        return $this->hasOne(PosUser::class, ['username' => 'shiftOutUsername']);
    }

    public function getShiftLogCash() {
        return $this->hasMany(ShiftLogCash::class, ['shiftID' => 'shiftID'])
                ->with(['shiftInUser','shiftOutUser']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public static function findActive() {
        $branchID = Setting::getCurrentBranch();

        return ShiftLog::find()
                ->andWhere(['IS', 'shiftOutTime', null])
                ->andWhere(['branchID' => $branchID])
                ->one();
    }

    public static function getShiftInDate() {
        $branchID = Setting::getCurrentBranch();

        return ShiftLog::find()
                ->select(new Expression('DATE(shiftInTime)'))
                ->andWhere(['IS', 'shiftOutTime', null])
                ->andWhere(['branchID' => $branchID])
                ->scalar();
    }

    public static function syncUpdate($shiftID, $syncDate, $salesShiftHeadIDs = null) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            ShiftLog::updateAll([
                'syncDate' => $syncDate
                ],
                ['AND', ['branchID' => $branchID], ['shiftID' => $shiftID]
            ]);

            ShiftLogDetail::updateAll([
                'syncDate' => $syncDate
                ], ['shiftID' => $shiftID]
            );

            ShiftLogCash::updateAll([
                'syncDate' => $syncDate
                ], ['shiftID' => $shiftID]
            );

            ShiftLogMode::updateAll([
                'syncDate' => $syncDate
                ], ['shiftID' => $shiftID]
            );

            if ($salesShiftHeadIDs) {

                SalesShiftPaymentHead::updateAll([
                    'syncDate' => $syncDate
                    ],
                    ['IN', 'salesShiftPaymentHeadID', $salesShiftHeadIDs]
                );

                SalesShiftPaymentDetail::updateAll([
                    'syncDate' => $syncDate
                    ],
                    ['IN', 'salesShiftPaymentHeadID', $salesShiftHeadIDs]
                );

                SalesShiftPaymentDenom::updateAll([
                    'syncDate' => $syncDate
                    ],
                    ['IN', 'salesShiftPaymentHeadID', $salesShiftHeadIDs]
                );
                
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }

}
