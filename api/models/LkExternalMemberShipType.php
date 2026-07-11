<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_externalmembershiptype".
 *
 * @property string $externalMembershipTypeID
 * @property string $externalMembershipTypeName
 */
class LkExternalMemberShipType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'lk_externalmembershiptype';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['externalMembershipTypeID'], 'required'],
            [['externalMembershipTypeID'], 'string', 'max' => 20],
            [['externalMembershipTypeName'], 'string', 'max' => 50],
            [['externalMembershipTypeID'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'externalMembershipTypeID' => 'External Membership Type ID',
            'externalMembershipTypeName' => 'External Membership Type Name',
        ];
    }
}
