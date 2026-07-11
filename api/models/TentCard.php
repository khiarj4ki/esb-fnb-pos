<?php

namespace app\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * This is the model class for table "ms_tentcard".
 *
 * @property int $tentCardID
 * @property int $branchID
 * @property string $name
 * @property string $image
 * @property int $flagFeatured
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class TentCard extends ActiveRecord {
    /**
     * @inheritdoc
     */
    public $fileUpload;

    public static function tableName() {
        return 'ms_tentcard';
    }

    public function behaviors() {
        return [
            [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'createdBy',
                'updatedByAttribute' => 'editedBy',
            ],
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'createdDate',
                'updatedAtAttribute' => 'editedDate',
                'value' => function() {
                    return date('Y-m-d H:i:s');
                }
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['branchID', 'name'], 'required'],
            [['branchID', 'flagFeatured'], 'integer'],
            [['image'], 'string'],
            [['createdDate', 'editedDate'], 'safe'],
            [['name'], 'string', 'max' => 100],
            [['createdBy', 'editedBy'], 'string', 'max' => 50],
            [['flagActive'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'tentCardID' => 'Tent Card ID',
            'branchID' => 'Branch',
            'name' => 'Name',
            'image' => 'Image',
            'flagFeatured' => 'Featured Card',
            'flagActive' => Yii::t('app', 'Status'),
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
        ];
    }
    
    public static function findActiveMenuTentCard() {
        $imageTentCardLoc = Url::to('@web/images/tent-card/', true);
        $tentCardModel = TentCard::find()
                ->andWhere([TentCard::tableName() . '.flagActive' => 1])
                ->andWhere([TentCard::tableName() . '.flagFeatured' => 1])
                ->orderBy(TentCard::tableName() . '.name')
                ->all();
        
        $tentCardData = [];
        $i = 0;
        foreach ($tentCardModel as $model) {
            $tentCardData[$i]['tentCardID'] = $model->tentCardID;
            $tentCardData[$i]['branchID'] = $model->branchID;
            $tentCardData[$i]['name'] = $model->name;
            $tentCardData[$i]['image'] = $model->image ? $imageTentCardLoc . $model->image : null;
            $tentCardData[$i]['flagFeatured'] = $model->flagFeatured;
            $tentCardData[$i]['flagActive'] = $model->flagActive;
            $tentCardData[$i]['createdBy'] = $model->createdBy;
            $tentCardData[$i]['createdDate'] = $model->createdDate;
            $tentCardData[$i]['editedBy'] = $model->editedBy;
            $tentCardData[$i]['editedDate'] = $model->editedDate;
            $i++;
        }
        return $tentCardData;
    }
    
    public static function findActiveMenuAllTentCard() {
        $imageTentCardLoc = Url::to('@web/images/tent-card/', true);
        $tentCardModel = TentCard::find()
                ->andWhere([TentCard::tableName() . '.flagActive' => 1])
                ->orderBy(TentCard::tableName() . '.name')
                ->all();
        
        $tentCardData = [];
        $i = 0;
        foreach ($tentCardModel as $model) {
            $tentCardData[$i]['tentCardID'] = $model->tentCardID;
            $tentCardData[$i]['branchID'] = $model->branchID;
            $tentCardData[$i]['name'] = $model->name;
            $tentCardData[$i]['image'] = $model->image ? $imageTentCardLoc . $model->image : null;
            $tentCardData[$i]['flagFeatured'] = $model->flagFeatured;
            $tentCardData[$i]['flagActive'] = $model->flagActive;
            $tentCardData[$i]['createdBy'] = $model->createdBy;
            $tentCardData[$i]['createdDate'] = $model->createdDate;
            $tentCardData[$i]['editedBy'] = $model->editedBy;
            $tentCardData[$i]['editedDate'] = $model->editedDate;
            $i++;
        }
        return $tentCardData;
    }

}
