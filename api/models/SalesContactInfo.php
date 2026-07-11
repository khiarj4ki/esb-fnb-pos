<?php
namespace app\models;

use yii\db\ActiveRecord;
use Exception;
use Yii;

/**
 * This is the model class for table "tr_salescontactinfo".
 *
 * @property int $salesContactInfoID
 * @property string $salesNum
 * @property string $customerPhoneNum
 */
class SalesContactInfo extends ActiveRecord {
    const SCENARIO_SAVE_CONTACT = 'save contact';
    const SCENARIO_DELETE_CONTACT = 'delete contact';
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salescontactinfo';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesContactInfoID'], 'safe'],
            [['salesNum', 'customerPhoneNum'], 'required', 'on' => self::SCENARIO_SAVE_CONTACT],
            [['salesNum'], 'required', 'on' => self::SCENARIO_DELETE_CONTACT],
            [['salesNum', 'customerPhoneNum'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'salesContactInfoID' => 'Sales Contact Info ID',
            'salesNum' => 'Sales Num',
            'customerPhoneNum' => 'Customer Phone Num',
        ];
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        parent::afterSave($insert, $changedAttributes);
    }

    public static function checkSalesPhoneNumber($salesNum) {
        $salesContactInfoModel = SalesContactInfo::find()
            ->where(['salesNum' => $salesNum])
            ->one();

        return $salesContactInfoModel ? $salesContactInfoModel : false;
    }

    public function saveModel()
    {
        if (!$this->validate()) {
            return false;
        }

        try {
            $model = self::find()
                ->where(['salesNum' => $this->salesNum])
                ->one();

            if (!$model) {
                $model = new SalesContactInfo();
                $model->salesNum = $this->salesNum;
                $model->customerPhoneNum = $this->customerPhoneNum;
            } else {
                if ($model->customerPhoneNum != $this->customerPhoneNum) {
                    $model->customerPhoneNum = $this->customerPhoneNum;
                } else {
                    return true;
                }
            }

            if (!$model->save()) {
                throw new Exception('Failed to save data', 500);
            }

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function deleteModel()
    {
        if (!$this->validate()) {
            return false;
        }

        try {
            $model = self::find()
                ->where([
                    'salesNum' => $this->salesNum
                ])->one();

            if ($model) {
                if (!$model->delete()) {
                    throw new Exception('Failed to delete data', 500);
                }
            }

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }
}
