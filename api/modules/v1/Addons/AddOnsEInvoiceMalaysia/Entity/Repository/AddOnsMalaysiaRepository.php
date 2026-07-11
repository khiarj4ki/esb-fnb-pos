<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Entity\Repository;

use app\models\Branch;
use app\models\Menu;
use app\models\PaymentMethod;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesPayment;
use app\models\Setting;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaException;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaExceptionInterface;
use Exception;
use Yii;
use yii\db\Query;

class AddOnsMalaysiaRepository implements AddOnsMalaysiaRepositoryInterface
{

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function findOrFailSalesHead(string $salesNum): array {
        $salesHead =  (new Query())
            ->select([
                'salesDate' => 'sh.salesDate',
                'subTotal' => 'sh.subTotal',
                'tax' => 'sh.vatTotal',
                'otherTax' => 'sh.otherTaxTotal',
                'grandTotal' => 'sh.grandTotal',
                'additionalInfo' => 'sh.additionalInfo',
            ])
            ->from(['sh' => SalesHead::tableName()])
            ->andWhere(['sh.salesNum' => $salesNum])
            ->one();

        if (!$salesHead) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SALES_HEAD_NOT_FOUND);
        }

        return $salesHead;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function getSalesPayment(string $salesNum): array
    {
        $salesPayment =  (new Query())
            ->select([
                'paymentMethod' => 'pm.paymentMethodName',
                'paymentAmount' => 'sp.paymentAmount',
            ])
            ->from(['sp' => SalesPayment::tableName()])
            ->leftJoin(['pm' => PaymentMethod::tableName()], 'sp.paymentMethodID = pm.paymentMethodID')
            ->andWhere(['sp.salesNum' => $salesNum])
            ->all();
        if (!$salesPayment) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SALES_PAYMENT_NOT_FOUND);
        }

        return $salesPayment;

    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function getSalesMenu(string $salesNum): array
    {
        $salesMenu = (new Query())
            ->select([
                'menuName' => 'mm.menuName',
                'qty' => 'sm.qty',
                'price' => 'sm.price',
            ])
            ->from(['sm' => SalesMenu::tableName()])
            ->leftJoin(['mm' => Menu::tableName()], 'sm.menuID = mm.menuID')
            ->andWhere(['sm.salesNum' => $salesNum])
            ->all();
        if (!$salesMenu) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SALES_MENU_NOT_FOUND);
        }

        return $salesMenu;
    }

    /**
     * @param string $key1
     * @param string $key2
     * @return string|null
     * @throws Exception
     */
    public function findOrFailOMSUrlSetting(string $key1, string $key2): ?string {
        $setting = Setting::find()
            ->andWhere(['key1' => $key1])
            ->andWhere(['key2' => $key2])
            ->one();

        if (!$setting) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SALES_HEAD_NOT_FOUND);
        }

        return $setting->value1;
    }

    /**
     * @throws Exception
     */
    public function findOrFailOMSCredentialSetting(string $key1, string $key2): ?string {
        $setting = Setting::find()
            ->andWhere(['key1' => $key1])
            ->andWhere(['key2' => $key2])
            ->one();

        if (!$setting) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SALES_HEAD_NOT_FOUND);
        }

        // TODO: encryption
        return $setting->value1;
    }

    /**
     * @throws Exception
     */
    public function findOrAddOnsUrlEInvoiceLDHNSetting(string $key1, string $key2): ?string {
        $setting = Setting::find()
            ->andWhere(['key1' => $key1])
            ->andWhere(['key2' => $key2])
            ->one();

        if (!$setting) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SALES_HEAD_NOT_FOUND);
        }

        return Yii::$app->security->decryptByKey(base64_decode($setting->value1),
            Yii::$app->params['key']);
    }

    /**
     * @return int
     * @throws Exception
     */
    public function findOrFailBranchsettingID(): int
    {
        $setting = (int) Setting::getCurrentBranch();
        if (!$setting) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SETTING_BRANCH_ID_NOTFOUND);
        }

        return $setting;
    }

    /**
     * @param int $branchID
     * @return string
     * @throws Exception
     */
    public function findOrFailBranch(int $branchID): string
    {
        $branch = Branch::find()
            ->select([
                'branchName',
            ])
            ->where(['branchID' => $branchID])
            ->one();
        if (!$branch) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::SETTING_BRANCH_ID_NOTFOUND);
        }

        return $branch ? $branch->branchName : 'ESB Branch';
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getApiKey(): string
    {
        $setting = Setting::getApiKey();
        if (!$setting) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_REQUEST_VALIDATION);
        }

        return $setting;
    }

}