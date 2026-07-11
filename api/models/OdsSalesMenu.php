<?php

namespace app\models;

use app\components\AppHelper;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmenu".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $batchID
 * @property int $menuRefID
 * @property int $menuGroupID
 * @property int $menuID
 * @property string $qty
 * @property string $price
 * @property string $originalPrice
 * @property string $discount
 * @property string $discountValue
 * @property string $inclusiveDiscountValue
 * @property string $otherTax
 * @property string $otherTaxValue
 * @property string $vat
 * @property string $vatValue
 * @property int $otherTaxOnVat
 * @property string $total
 * @property string $notes
 * @property int $statusID
 * @property int $promotionDetailID
 * @property int $menuPromotionID
 * @property string $cancelNotes
 * @property string $salesType
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * @property string $netPrice
 * 
 * @property SalesHead $salesHead
 * @property Menu $menu
 * @property BranchMenu $branchMenu
 * @property SalesMenu $parentSalesMenu
 * @property SalesMenu[] $childSalesMenus
 * @property SalesMenuExtra[] $salesExtras
 * @property Status $status
 * @property PosUser $creator
 * @property PosUser $editor
 */
class OdsSalesMenu extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tr_salesmenu';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    //ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy'],
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
            [['salesNum', 'menuID', 'qty', 'price', 'discount', 'discountValue', 'otherTax', 'otherTaxValue', 'vat', 'vatValue', 'otherTaxOnVat', 'total', 'statusID', 'originalPrice'], 'required'],
            [['localID', 'batchID', 'menuRefID', 'menuGroupID', 'menuID', 'otherTaxOnVat', 'statusID', 'promotionDetailID', 'menuPromotionID', 'flagPending'], 'integer'],
            [['batchID', 'menuRefID', 'menuGroupID', 'promotionDetailID', 'menuPromotionID'], 'default', 'value' => 0],
            [['statusID'], 'default', 'value' => 13],
            [['qty', 'price', 'discount', 'discountValue', 'inclusiveDiscountValue', 'otherTax', 'otherTaxValue', 'vat', 'vatValue', 'total', 'originalPrice', 'inclusivePrice'], 'number'],
            [['createdDate', 'editedDate', 'syncDate', 'subsID', 'pendingOrder', 'flagPending', 'promotionVoucherCode'], 'safe'],
            [['salesNum', 'salesType', 'promotionVoucherCode'], 'string', 'max' => 50],
            [['ID'], 'safe', 'on' => 'NEW_INSTALL'],
            [['cancelNotes', 'customMenuName', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['notes'], 'string', 'max' => 200],
            [['notes', 'cancelNotes'], 'default', 'value' => ''],
            [['customMenuName', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'batchID' => 'Batch ID',
            'menuRefID' => 'Menu Ref ID',
            'menuGroupID' => 'Menu Group ID',
            'menuID' => 'Menu ID',
            'customMenuName' => 'Custom Menu Name',
            'qty' => 'Qty',
            'price' => 'Price',
            'originalPrice' => 'Original Price',
            'discount' => 'Discount',
            'otherTax' => 'Other Tax',
            'vat' => 'Vat',
            'otherTaxOnVat' => 'Other Tax On Vat',
            'total' => 'Total',
            'notes' => 'Notes',
            'statusID' => 'Status ID',
            'promotionDetailID' => 'Promotion Detail ID',
            'menuPromotionID' => 'Menu Promotion ID',
            'cancelNotes' => 'Cancel Notes',
            'salesType' => 'Sales Type',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'syncDate' => 'Sync Date'
        ];
    }

    public function getSalesHead()
    {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public function getMenu()
    {
        return $this->hasOne(Menu::class, ['menuID' => 'menuID']);
    }

    public function getBranchMenu()
    {
        $branchID = Setting::getCurrentBranch();

        return $this->hasOne(BranchMenu::class, ['menuID' => 'menuID'])
            ->andOnCondition([BranchMenu::tableName() . '.branchID' => $branchID]);
    }

    public function getParentSalesMenu()
    {
        return $this->hasOne(OdsSalesMenu::class, ['ID' => 'menuRefID']);
    }

    public function getChildSalesMenus()
    {
        return $this->hasMany(OdsSalesMenu::class, ['menuRefID' => 'ID'])
            ->andOnCondition(['AND', 'ID <> menuRefID', 'menuRefID <> 0']);
    }

    public function getSalesMenuCompletionKitchen()
    {
        return $this->hasMany(SalesMenuCompletion::class, ['salesMenuID' => 'ID'])
            ->andOnCondition(['typeID' => 1])
            ->orderBy(SalesMenuCompletion::tableName() . '.completedDate DESC');
    }

    public function getSalesMenuCompletionChecker()
    {
        return $this->hasMany(SalesMenuCompletion::class, ['salesMenuID' => 'ID'])
            ->andOnCondition(['typeID' => 2])
            ->orderBy(SalesMenuCompletion::tableName() . '.completedDate DESC');
    }

    public function getSalesExtras()
    {
        return $this->hasMany(
            SalesMenuExtra::class,
            ['salesNum' => 'salesNum', 'menuDetailID' => 'ID']
        );
    }

    public function getPromotion()
    {
        return $this->hasOne(
            PromotionHead::class,
            ['promotionID' => 'promotionDetailID']
        );
    }

    public function getStatus()
    {
        return $this->hasOne(Status::class, ['statusID' => 'statusID']);
    }

    public function getCreator()
    {
        return $this->hasOne(PosUser::class, ['username' => 'createdBy']);
    }

    public function getEditor()
    {
        return $this->hasOne(PosUser::class, ['username' => 'editedBy']);
    }

    public function getMenuGroup()
    {
        return $this->hasOne(MenuGroup::class, ['menuGroupID' => 'menuGroupID']);
    }
}
