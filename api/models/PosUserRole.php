<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_posuserrole".
 *
 * @property int $posUserRoleID
 * @property string $posRoleDesc
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property PosUserAccess[] $userAccesses
 */
class PosUserRole extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_posuserrole';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['flagActive', 'createdBy', 'createdDate'], 'required'],
            [['flagActive'], 'integer'],
            [['posUserRoleID', 'createdDate', 'editedDate'], 'safe'],
            [['posRoleDesc', 'createdBy', 'editedBy'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'posUserRoleID' => 'Pos User Role ID',
            'posRoleDesc' => 'Pos Role Desc',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function getUserAccesses() {
        return $this->hasMany(PosUserAccess::class,
                ['posUserRoleID' => 'posUserRoleID']);
    }

}
