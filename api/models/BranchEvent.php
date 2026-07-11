<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_branchevent".
 *
 * @property int $ID
 * @property int $branchID
 * @property string $eventDate
 * @property string $refNum
 * @property string $eventSubject
 * @property string $eventDescription
 * @property string $createdBy
 * @property string $syncDate
 */
class BranchEvent extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_branchevent';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['branchID', 'eventDate', 'refNum', 'eventSubject', 'eventDescription', 'createdBy'], 'required'],
            [['branchID'], 'integer'],
            [['eventDate', 'syncDate'], 'safe'],
            [['refNum'], 'string', 'max' => 20],
            [['eventSubject'], 'string', 'max' => 50],
            [['eventDescription'], 'string'],
            [['createdBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'branchID' => 'Branch ID',
            'eventDate' => 'Event Date',
            'refNum' => 'Ref Num',
            'eventSubject' => 'Event Subject',
            'eventDescription' => 'Event Description',
            'createdBy' => 'Created By',
            'syncDate' => 'Sync Date'
        ];
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        //$this->syncDate = null;

        return true;
    }

    public static function syncUpdate($ID, $syncDate) {
        $branchID = Setting::getCurrentBranch();

        BranchEvent::updateAll([
            'syncDate' => $syncDate
            ], ['AND', ['branchID' => $branchID], ['ID' => $ID]
        ]);
    }
    
    public static function getStringArray($descriptionDetails) {
        $description = '';
        foreach ($descriptionDetails as $key => $descriptionDetail) {
            if(is_array($descriptionDetail)) {
                $description .= is_string($key) ?
                "{ ". $key . " } :  \n" . self::getStringArray($descriptionDetail) . "\n" :
                $key + 1 . ":  \n" . self::getStringArray($descriptionDetail) . "\n";
            } else {
                $description .= $key . ': ' . $descriptionDetail . "\n";
            }
        }
        return $description;
    }

    public static function getPaymentHistory($salesNum) 
    {
        $checkEditSalesLog = BranchEvent::find()
            ->where(['eventSubject' => 'Edit Payment'])
            ->andWhere(['refNum' => $salesNum])
            ->orderBy(['ID' => SORT_DESC])
            ->all();

        if ($checkEditSalesLog && count($checkEditSalesLog) > 0) {
            $historyPayment = $checkEditSalesLog[0];

            return $historyPayment;
        } else {
            $checkSalesPaymentLog = BranchEvent::find()
                ->where(['IN', 'eventSubject', ['Save Payment', 'Save Payment ESO', 'Save Payment KIOSK']])
                ->andWhere(['refNum' => $salesNum])
                ->one();

            return $checkSalesPaymentLog;
        }
    }
}
