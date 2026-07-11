<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_posuseraccess".
 *
 * @property int $posUserRoleID
 * @property string $filterAccessID
 * @property int $hasAccess
 * 
 * @property PosFilterAccess $filterAccess
 */
class PosUserAccess extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_posuseraccess';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['posUserRoleID', 'filterAccessID', 'hasAccess'], 'required'],
            [['posUserRoleID', 'hasAccess'], 'integer'],
            [['filterAccessID'], 'string', 'max' => 10],
            [['posUserRoleID', 'filterAccessID'], 'unique', 'targetAttribute' => ['posUserRoleID', 'filterAccessID']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'posUserRoleID' => 'Pos User Role ID',
            'filterAccessID' => 'Filter Access ID',
            'hasAccess' => 'Has Access',
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['posAccessID'] = function ($model) {
            return $model->filterAccess->posAccessID;
        };
        $fields['description'] = function ($model) {
            return $model->filterAccess->description;
        };
        $fields['subNodes'] = function ($model) {
            return $model->filterAccess->subNodes;
        };
        return $fields;
    }

    public function getFilterAccess() {
        return $this->hasOne(PosFilterAccess::class,
                ['filterAccessID' => 'filterAccessID']);
    }

}
