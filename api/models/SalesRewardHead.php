<?php

namespace app\models;

use Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesrewardhead".
 * 
 * @property string $salesNum
 * @property string $rewardType
 */

class SalesRewardHead extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tr_salesrewardhead';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['salesNum', 'rewardType'], 'required']
        ];
    }

    public static function adjustSalesRewardHead($externalMembershipTypeID, $promotionVoucherCode, $salesNum, $rewardType)
    {
        $salesRewardHeadModel = SalesRewardHead::findOne([
            'salesNum' => $salesNum
        ]);

        switch ($externalMembershipTypeID) {
            case 'looplite':
                if (strlen($promotionVoucherCode) > 0) {
                    if ($salesRewardHeadModel) {
                        $salesRewardHeadModel->rewardType = $rewardType;
                    } else {
                        $salesRewardHeadModel = new SalesRewardHead();
                        $salesRewardHeadModel->salesNum = $salesNum;
                        $salesRewardHeadModel->rewardType = $rewardType;
                    }

                    if (!$salesRewardHeadModel->save()) {
                        throw new Exception("Failed to save reward order");
                    }
                } else {
                    if ($salesRewardHeadModel) {
                        $salesRewardHeadModel->delete();
                    }
                }
                break;
            default:
                if ($salesRewardHeadModel) {
                    $salesRewardHeadModel->delete();
                }
                break;
        }
    }
}
