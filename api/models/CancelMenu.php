<?php

namespace app\models;

use app\components\AppHelper;
use Exception;
use stdClass;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\data\ArrayDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_cancelmenu".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $branchID
 * @property int $menuRefID
 * @property int $menuGroupID
 * @property int $menuID
 * @property int $menuExtraID
 * @property string $customMenuName
 * @property int $qty
 * @property string $originalPrice
 * @property string $price
 * @property string $inclusivePrice
 * @property string $createdBy
 * @property string $createdDate
 * @property string $syncDate
 * 
 */

class CancelMenu extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_cancelmenu';
    }
    
    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy']
                ],
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID', 'branchID'], 'integer'],
            [
                [
                    'createdDate', 'syncDate',
                    'menuRefID', 'menuGroupID',
                    'menuID', 'menuExtraID','qty', 
                    'originalPrice', 'price', 'inclusivePrice'
                ], 
                'safe'
            ],
            [['salesNum'], 'string', 'max' => 50],
            [['customMenuName','createdBy'], 'string', 'max' => 100]
        ];
    }


    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->branchID  = Setting::getCurrentBranch();
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }
    
    public static function syncUpdate($ID, $syncDate) {

        CancelMenu::updateAll(
            [
                'syncDate' => $syncDate
            ],
            ['AND', 
                ['ID' => $ID]
            ]);
    }


}