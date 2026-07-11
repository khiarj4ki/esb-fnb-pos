<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_eso_log".
 *
 * @property int $ID
 * @property int $branchID
 * @property string $eventDate
 * @property string $refNum
 * @property string $eventSubject
 * @property string $eventDescription
 * @property string $eventType
 * @property int $isSuccess
 * @property string $syncDate
 */
class EsoLogEvent extends ActiveRecord {

    const RUN_EVENT = 'run';

    public $pages;
    public $limit;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_eso_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['branchID', 'eventDate', 'refNum', 'eventSubject', 'eventDescription'], 'required'],
            [['branchID'], 'integer'],
            [['eventDate', 'syncDate','eventType','isSuccess','pages','limit'], 'safe'],
            [['refNum'], 'string', 'max' => 20],
            [['eventSubject'], 'string', 'max' => 50],
            [['eventDescription'], 'string']
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
            'eventType' => 'Event Type',
            'isSuccess' => 'Status',
            'syncDate' => 'Sync Date'
        ];
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        return true;
    }

    public static function syncUpdate($iD, $syncDate) {
        $branchID = Setting::getCurrentBranch();

        EsoLogEvent::updateAll([
                'syncDate' => $syncDate
            ],
            [
                'AND',
                ['branchID' => $branchID],
                ['ID' => $iD],
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

    public function getListErrorEso() {

        $this->pages = $this->pages ?? 0;
        $this->limit = $this->limit ?? 20;

        $query = EsoLogEvent::find()
                    ->where(['isSuccess' => 0])
                    ->andWhere(['IN', 'eventType', [ self::RUN_EVENT ]])
                    ->andFilterWhere(['refNum' => $this->refNum]);

        if(!$this->refNum) {
            $query->offset($this->pages)
                  ->limit($this->limit);
        }
                   
        return $query->all();
    }
}
