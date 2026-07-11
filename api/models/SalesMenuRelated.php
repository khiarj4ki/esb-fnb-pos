<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "tr_salesmenurelated".
 *
 * @property int $ID
 * @property string $salesNum
 * @property int $salesMenuID
 * @property int $mainMenuID
 * @property float $mainMenuQty
 * @property int $relatedMenuID
 * @property float $relatedMenuQty
 * 
 */
class SalesMenuRelated extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesmenurelated';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'salesMenuID', 'relatedMenuID'], 'required'],
            [['salesNum'], 'string', 'max' => 50],
            [['mainMenuQty', 'relatedMenuQty'], 'number'],
            [['salesMenuID', 'mainMenuID', 'relatedMenuID'], 'integer']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'salesNum' => 'Sales Num',
            'salesMenuID' => 'Sales Menu ID',
            'mainMenuID' => 'Main Menu ID',
            'mainMenuQty' => 'Main Menu Qty',
            'relatedMenuID' => 'Related Menu ID',
            'relatedMenuQty' => 'Related Menu Qty'
        ];
    }

    public static function saveSalesMenuRelated($salesNum, $salesMenuID, $salesMenu, $salesMenus) {
        try {
            if (isset($salesMenu['mainMenuID']) && !empty($salesMenu['mainMenuID'])) {
                if ($salesMenu['statusID'] == '12') {
                    return true;
                }
                $salesMenuRelatedModel = SalesMenuRelated::findOne(['salesNum' => $salesNum, 'salesMenuID' => $salesMenuID]);
                if (!$salesMenuRelatedModel) {
                    $salesMenuRelatedModel = new SalesMenuRelated();
                    $salesMenuRelatedModel->salesNum = $salesNum;
                    $salesMenuRelatedModel->salesMenuID = $salesMenuID;
                }

                $mainMenuID = $salesMenu['mainMenuID'];
                $mainMenuQty = 0;
                $key = array_search($mainMenuID, array_column($salesMenus, 'menuID'));
                if ($key > -1 && $key !== false) {
                    $mainMenuQty = $salesMenus[$key]['qty'];
                }
                $salesMenuRelatedModel->mainMenuID = $mainMenuID;
                $salesMenuRelatedModel->mainMenuQty = $mainMenuQty;
                $salesMenuRelatedModel->relatedMenuID = $salesMenu['menuID'];
                $salesMenuRelatedModel->relatedMenuQty = $salesMenu['qty'];
                
                if (!$salesMenuRelatedModel->save()) {
                    throw new Exception($salesMenuRelatedModel->getErrors());
                }
            }
            return true;
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }
}
