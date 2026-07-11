<?php

namespace app\models;

use app\models\forms\Logging;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "ms_member".
 *
 * @property int $memberID
 * @property string $memberName
 * @property int $memberTypeID
 * @property string $memberCode
 * @property int $genderID
 * @property string $memberBirthDate
 * @property string $memberAddress
 * @property string $memberPhone
 * @property string $memberEmail
 * @property bool $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * 
 * @property Gender $gender
 */
class Member extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'ms_member';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate', 'editedDate'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy', 'editedBy'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedBy'],
                ],
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['memberName', 'memberTypeID', 'memberCode', 'genderID'], 'required'],
            [['memberTypeID', 'genderID'], 'integer'],
            [['memberID', 'memberBirthDate', 'createdDate', 'editedDate', 'syncDate'], 'safe'],
            [['flagActive'], 'boolean'],
            [['flagActive'], 'default', 'value' => 1],
            [['memberName', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['memberCode', 'memberPhone'], 'string', 'max' => 20],
            [['memberAddress'], 'string', 'max' => 200],
            [['memberEmail'], 'string', 'max' => 50],
            [['memberAddress', 'memberPhone', 'memberEmail'], 'default', 'value' => ''],
            [['memberCode'], 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'memberID' => 'Member ID',
            'memberName' => 'Member Name',
            'memberTypeID' => 'Member Type ID',
            'memberCode' => 'Member Code',
            'genderID' => 'Gender ID',
            'memberBirthDate' => 'Member Birth Date',
            'memberAddress' => 'Member Address',
            'memberPhone' => 'Member Phone',
            'memberEmail' => 'Member Email',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields()
    {
        $fields = parent::fields();
        $fields['genderName'] = function ($model) {
            return $model->gender->genderName;
        };

        return $fields;
    }

    public function getGender()
    {
        return $this->hasOne(Gender::class, ['genderID' => 'genderID']);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        Logging::save(
            strval($this->memberID),
            $insert ? Logging::CREATE_MEMBER : Logging::EDIT_MEMBER,
            $this->getAttributes()
        );

        parent::afterSave($insert, $changedAttributes);
    }

    public static function findActive()
    {
        return Member::find()->andWhere([Member::tableName() . '.flagActive' => 1])
            ->with('gender')
            ->orderBy(Member::tableName() . '.memberName');
    }

    public static function syncUpdate($memberID, $syncDate)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            Member::updateAll(
                [
                    'syncDate' => $syncDate
                ],
                ['memberID' => $memberID]
            );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }
}
