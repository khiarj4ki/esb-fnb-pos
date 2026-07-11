<?php

namespace app\models;

use Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesrewardmenu".
 * 
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property string $rewardType
 */

class SalesRewardMenu extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tr_salesrewardmenu';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ID', 'localID', 'salesNum', 'rewardType'], 'required']
        ];
    }

    public static function adjustSalesRewardMenu($externalMembershipTypeID, $salesMenu, $rewardType)
    {
        $salesRewardMenuModel = SalesRewardMenu::findOne([
            'ID' => $salesMenu->ID,
            'localID' => $salesMenu->localID,
            'salesNum' => $salesMenu->salesNum
        ]);

        switch ($externalMembershipTypeID) {
            case 'looplite':
                if (strlen($salesMenu->promotionVoucherCode) > 0) {
                    if ($rewardType == null) {
                        $rewardType = $salesMenu->salesRewardMenu ? $salesMenu->salesRewardMenu->rewardType : null;
                    }

                    if ($salesRewardMenuModel) {
                        $salesRewardMenuModel->rewardType = $rewardType;
                    } else {
                        $salesRewardMenuModel = new SalesRewardMenu();
                        $salesRewardMenuModel->ID = $salesMenu->ID;
                        $salesRewardMenuModel->localID = $salesMenu->localID;
                        $salesRewardMenuModel->salesNum = $salesMenu->salesNum;
                        $salesRewardMenuModel->rewardType = $rewardType;
                    }

                    if (!$salesRewardMenuModel->save()) {
                        throw new Exception("Failed to save reward menu");
                    }
                } else {
                    if ($salesRewardMenuModel) {
                        $salesRewardMenuModel->delete();
                    }
                }
                break;
            default:
                if ($salesRewardMenuModel) {
                    $salesRewardMenuModel->delete();
                }
                break;
        }
    }
}
