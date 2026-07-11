<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "tr_notification".
 *
 * @property int $tableID
 * @property string $action
 * @property string $createdDate
 * 
 * @property Table $table
 */
class Notification extends ActiveRecord {
    const ACTION_WAITER = 'WAITER';
    const ACTION_BILL = 'BILL';
    const ACTION_CAMPAIGN = 'WIN';

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_notification';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'action'], 'required'],
            [['tableID'], 'integer'],
            [['createdDate'], 'safe'],
            [['action'], 'string', 'max' => 50],
            [['tableID', 'action'], 'unique', 'targetAttribute' => ['tableID', 'action']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'tableID' => 'Table ID',
            'action' => 'Action',
            'createdDate' => 'Created Date',
        ];
    }

    public function getTable() {
        return $this->hasOne(Table::class, ['tableID' => 'tableID']);
    }

    public static function saveNotif($tableID, $action) {
        $billPrintCount = null;
        if ($action === self::ACTION_BILL) {
            $billPrintCount = 0;
        }

        $salesModel = SalesHead::findOutstanding()
            ->joinWith('salesMergeTables')
            ->andWhere(['OR',
                [SalesHead::tableName() . '.tableID' => $tableID],
                [SalesMergeTable::tableName() . '.tableID' => $tableID]
            ])
            ->andFilterWhere(['billingPrintCount' => $billPrintCount])
            ->one();
        if (!$salesModel) {
            return true;
        }
        if ($action === self::ACTION_BILL) {
            SalesHead::updateAll(['lockTable' => 1], ['salesNum' => $salesModel->salesNum]);
        }

        $notifModel = Notification::find()
            ->andWhere(['tableID' => $tableID])
            ->andWhere(['action' => $action])
            ->one();
        if ($notifModel) {
            $notifModel->createdDate = new Expression('NOW()');
        } else {
            $notifModel = new Notification();
            $notifModel->tableID = $tableID;
            $notifModel->action = $action;
            $notifModel->createdDate = new Expression('NOW()');
        }
        $notifModel->save();

        return true;
    }

}
