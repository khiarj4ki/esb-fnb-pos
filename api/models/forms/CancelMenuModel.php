<?php
namespace app\models\forms;

use app\models\MenuExtra;
use Yii;
use yii\base\Model;
use yii\db\Exception;

use app\models\CancelMenu;

/**
 * This is the model class for table "tr_cancelmenu".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $branchID
 * @property int $menuRefID
 * @property int $menuGroupID
 * @property int $menuID
 * @property int $menuExtraID
 * @property string $customMenuName
 * @property int $qty
 * @property string $originalPrice
 * @property string $price
 * @property string $inclusivePrice
 * @property string $createdBy
 * @property string $createdDate
 * @property string $syncDate
 * 
 */

class CancelMenuModel extends Model {

    public $ID;
    public $localID;
    public $salesNum;
    public $branchID;
    public $menuRefID;
    public $menuGroupID;
    public $menuID;
    public $menuExtraID;
    public $customMenuName;
    public $qty;
    public $originalPrice;
    public $price;
    public $inclusivePrice;
    public $createdBy;
    public $createdDate;
    public $syncDate;

        /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID', 'branchID'], 'integer'],
            [
                [   
                    'createdDate', 'syncDate',
                    'menuRefID', 'menuGroupID',
                    'menuID', 'menuExtraID','qty', 
                    'originalPrice', 'price', 'inclusivePrice'
                ], 
                'safe'
            ],
            [['salesNum'], 'string', 'max' => 50],
            [['customMenuName','createdBy'], 'string', 'max' => 100]
        ];
    }

    public function saveModel() {

        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $cancelMenuModel = new CancelMenu();
            $cancelMenuModel->salesNum = $this->salesNum;
            $cancelMenuModel->menuRefID = $this->menuRefID;
            $cancelMenuModel->menuGroupID = $this->menuGroupID;
            $cancelMenuModel->menuID = $this->menuID;
            $cancelMenuModel->menuExtraID = $this->menuExtraID;
            $cancelMenuModel->customMenuName = $this->customMenuName;
            $cancelMenuModel->qty = $this->qty;
            $cancelMenuModel->originalPrice = $this->originalPrice;
            $cancelMenuModel->price = $this->price;
            $cancelMenuModel->inclusivePrice = $this->inclusivePrice;
 
            if($this->menuExtraID != '0'){
                $menuExtreModel = MenuExtra::find()
                    ->select('menuRefID')
                    ->where(['=', 'menuExtraID', $this->menuExtraID])
                    ->one();
                $cancelMenuModel->menuID = (int) $menuExtreModel->menuRefID;
            }

            if (!$cancelMenuModel->save()) {
                throw new Exception('Failed to save Cancel Menu');
            }
            

            $transaction->commit();
            return true;

        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('branchMenu', $ex->getMessage());
            return false;
        }
    }

}
