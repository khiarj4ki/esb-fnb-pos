<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * This is the model class for table "tr_saleslink".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property string $linkSalesNum
 * @property string $syncDate
 * 
 * @property SalesHead $salesHead
 * @property SalesHead $parentSalesHead
 */
class SalesLink extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_saleslink';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID'], 'integer'],
            [['salesNum', 'linkSalesNum'], 'required'],
            [['syncDate'], 'safe'],
            [['salesNum', 'linkSalesNum'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'linkSalesNum' => 'Link Sales Num',
            'syncDate' => 'Sync Date'
        ];
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'linkSalesNum']);
    }

    public function getParentSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public static function getGroupLinkedTotal($branchID, $startDate, $endDate) {
        //Grouping main sales number to get linked billing total
        return (new Query())
                ->select("a.salesNum, SUM(b.grandTotal - b.roundingTotal) 'total'")
                ->from(SalesLink::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.linkSalesNum = b.salesNum')
                ->andWhere(['b.branchID' => $branchID])
                ->andWhere(['>', 'b.salesDateOut', $startDate])
                ->andFilterWhere(['<', 'b.salesDateOut', $endDate])
                ->andWhere(['b.statusID' => 8])
                ->groupBy('a.salesNum');
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

}
