<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "ms_stireadersettingheader".
 *
 * @property integer $ID
 * @property string $companyID
 * @property string $companyCode
 * @property string $companyName
 * @property string $branchID
 * @property string $branchCode
 * @property string $branchName
 */
class MsReaderSettingTamanSafari extends ActiveRecord
{   
    public $tid;

    public static function tableName() {
        return 'ms_stireadersettingheader';
    }

    public function rules()
    {
        return [
            [
                [
                 'ID', 'companyName','companyID', 'companyCode', 'branchID','branchName','branchCode','TID',
                 'createdBy','createdDate','editedBy','editedDate','tid'
                ], 'safe'
            ],
        ];
    }

    public function getSettingReaderDetails() {
        return $this->hasMany(MsReaderSettingTamanSafariDetail::class, 
            ['headID' => 'ID']
        );
    }

    public function getDataReaderSettings()
    {
        $query = MsReaderSettingTamanSafari::find()
            ->select([
                'ms_stireadersettingheader.*',
                'tid' => new Expression('ms_stireadersettingdetail.TID')
            ])
            ->innerJoinWith('settingReaderDetails')
            ->where(['ms_stireadersettingheader.branchID' => $this->branchID])
            ->andWhere([MsReaderSettingTamanSafariDetail::tableName() . '.TID' => $this->tid])
            ->one();

            $result = [];
            if($query) {
                $result = [
                        'TID' => $query->tid,
                        'MID' => Yii::$app->params['stiReaderMID']
                ];
            }
             
        return $result;
    }
}
