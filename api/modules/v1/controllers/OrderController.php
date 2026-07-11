<?php

namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\components\AppHelper;
use app\models\Branch;
use app\models\forms\AddSalesChild;
use app\models\forms\BookTable;
use app\models\forms\CalculateTotal;
use app\models\forms\CancelTable;
use app\models\forms\CheckSplitBill;
use app\models\forms\DeleteSalesChild;
use app\models\forms\DeleteSalesMenuChild;
use app\models\forms\ExternalMember;
use app\models\forms\ExternalPaymentMethod;
use app\models\forms\LinkTable;
use app\models\forms\Logging;
use app\models\forms\MergeTable;
use app\models\forms\MoveItem;
use app\models\forms\MoveTable;
use app\models\forms\OrderCompletion;
use app\models\forms\OutstandingOrder;
use app\models\forms\PrintBill;
use app\models\forms\PrintChecker;
use app\models\forms\PrintOrder;
use app\models\forms\PrintQRTransaction;
use app\models\forms\SaveOnlineOrder;
use app\models\forms\SelfOrderTakeAway;
use app\models\forms\Steroid;
use app\models\forms\SyncSelfOrder;
use app\models\forms\UpdateMenuSplitBill;
use app\models\forms\UpdateOrder;
use app\models\forms\ValidateStock;
use app\models\MenuExtra;
use app\models\PaymentMethod;
use app\models\PaymentOnlineTrackingLog;
use app\models\PosExternalPayment;
use app\models\QuestionAnswer;
use app\models\SalesContactInfo;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesPlatformFee;
use app\models\Setting;
use app\models\Table;
use app\models\Terminal;
use app\models\TrCustomerTransaction;
use app\modules\v1\Member\Service\MemberService;
use app\modules\V1\Tables\CancelTable\Service\CancelTableService;
use Yii;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

class OrderController extends BaseController
{
    /**
     * @var MemberService
     */
    protected $memberService;
    /**
     * @var CancelTableService
     */
    protected $cancelTableService;

    /**
     * @param $id
     * @param $module
     * @param MemberService $memberService
     * @param CancelTableService $cancelTableService
     * @param array $config
     */
    public function __construct($id, $module, MemberService $memberService, CancelTableService $cancelTableService, array $config = []) {
        parent::__construct($id, $module, $config);

        $this->memberService = $memberService;
        $this->cancelTableService = $cancelTableService;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            [
                'index-take-away', 'sales-order-list', 'save-terminal'
            ]
        );
        return $behaviors;
    }

    public function actionIndex()
    {
        return SalesHead::findOutstanding()
            ->with('member')
            ->with('promotion')
            ->with('status')
            ->with('creator')
            ->with('editor')
            ->joinWith('table')
            ->with('salesMenuCompletionKitchen.salesMenu.menu.menuCategoryDetail')
            ->with('salesMenuCompletionKitchen.salesMenu.status')
            ->with('salesMenuCompletionKitchen.salesMenu.salesHead.visitPurpose')
            ->with('salesMenuCompletionKitchen.salesMenu.salesHead.table')
            ->with('salesMenuCompletionKitchen.salesMenu.childSalesMenus')
            ->with('salesMenuCompletionKitchen.salesMenu.salesExtras')
            ->with('salesMenuCompletionChecker.salesMenu.menu.menuCategoryDetail')
            ->with('salesMenuCompletionChecker.salesMenu.status')
            ->with('salesMenuCompletionChecker.salesMenu.salesHead.visitPurpose')
            ->with('salesMenuCompletionChecker.salesMenu.salesHead.table')
            ->with('salesMenuCompletionChecker.salesMenu.childSalesMenus')
            ->with('salesMenuCompletionChecker.salesMenu.salesExtras')
            ->orderBy('tableName')
            ->all();
    }

    public function actionGetOrderByBookNum()
    {
        $bookNum = $this->request->post('bookNum');
        return SalesHead::findOutstanding()
                    ->where(['bookNum' => $bookNum])
                    ->one();
    }

    public function actionDetail()
    {
        $salesNum = $this->request->post('salesNum');
        $salesModel = SalesHead::findOutstanding()
            ->with('table')
            ->with(['salesMenus' => function ($query) {
                $query->andWhere([
                    'OR',
                    ['menuRefID' => 0],
                    'menuRefID = localID'
                ])
                    ->orderBy(
                        SalesMenu::tableName() . '.batchID',
                        SalesMenu::tableName() . '.localID'
                    );
            }])
            ->with('salesMenus.status')
            ->with('salesMenus.menu')
            ->with(['salesMenus.childSalesMenus' => function ($query) {
                $query->andWhere('localID <> menuRefID');
            }])
            ->with('salesMenus.childSalesMenus.menu')
            ->with('salesMenus.childSalesMenus.status')
            ->andWhere([SalesHead::tableName() . '.salesNum' => $salesNum])
            ->one();
        if (!$salesModel) {
            throw new NotFoundHttpException(Yii::t('app', 'Order not found'));
        }

        $billSalesData = [];
        foreach ($salesModel->salesMenus as $salesMenu) {
            $packages = [];
            foreach ($salesMenu->childSalesMenus as $package) {
                $packageData = $this->getSalesMenuData($package);
                $packageData['packages'] = [];
                $packageData['extras'] = [];

                $packages[] = $packageData;
            }

            $salesMenuData = $this->getSalesMenuData($salesMenu);
            $salesMenuData['packages'] = $packages;
            // @TODO: Fetch extras from database
            $salesMenuData['extras'] = [];

            $billSalesData[] = $salesMenuData;
        }

        $salesData = $this->getSalesHeadData($salesModel);
        $salesData['billSalesMenu'] = $billSalesData;

        return $salesData;
    }

    public function actionIndexTakeAway()
    {
        $token = null;
        if ($this->request->headers->get('authorization')) {
            $token = str_replace(
                'Bearer ',
                '',
                $this->request->headers->get('authorization')
            );
        }

        return SalesHead::findOutstandingTakeAwayAsArray($token);
    }

    public function actionView()
    {
        $this->validatePost();

        return $this->findOutstandingOrder($this->request->post('salesNum'));
    }

    public function actionBookTable()
    {
        $this->validatePost();

        $bookModel = new BookTable([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$bookModel->save()) {
                throw new Exception(json_encode($bookModel->errors));
            }
            return $bookModel->salesNum;
        } catch (Exception $ex) {
            $errMsg = json_encode([
                'error' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile()
            ]);
            $this->returnSaveError($errMsg);
        }
    }

    /**
     * @return bool
     */
    public function actionUpdateMember(): bool
    {
        return $this->memberService->updateMember(
            $this->request->post()
        )->transform();
    }

    public function actionValidateSalesContactInfo() {
        $this->validatePost();
        
        $salesContactModel = new SalesContactInfo([
            'attributes' => $this->request->post('salesContactInfo')
        ]);

        $validateSalesContactNumber = ExternalMember::validateMemberPhoneNumber($salesContactModel);

        if (isset($validateSalesContactNumber->code)) {
            throw new HttpException($validateSalesContactNumber->code, Yii::t('app', $validateSalesContactNumber->message));
        }

        return $validateSalesContactNumber;
    }

    public function actionSaveSalesContactInfo() {
        $this->validatePost();

        $tableID = $this->request->post('tableID');
        $getErrorValidateTA = $this->request->post('tempValidateError');
        $flagMemberLoop = $this->request->post('flagMemberLoop');
        $flagSteroid = $this->request->post('flagSteroid');
        $flagUltraVoucher = $this->request->post('flagUltraVoucher');
        $salesContactModel = new SalesContactInfo([
            'attributes' => $this->request->post('salesContactInfo'),
            'scenario' => SalesContactInfo::SCENARIO_SAVE_CONTACT
        ]);

        if (isset($tableID) && $tableID !== 0 && !$flagMemberLoop && !$flagSteroid && !$flagUltraVoucher) {
            $validateSalesContactNumber = ExternalMember::validateMemberPhoneNumber($salesContactModel, $tableID);
        }

        try {
            if (!$salesContactModel->saveModel()) {
                throw new Exception(json_encode($salesContactModel->errors));
            }
            
            if (isset($getErrorValidateTA)) {
                ExternalMember::saveLoggingValidateQs($salesContactModel, $getErrorValidateTA);
            }

            if (isset($tableID) && $tableID !== 0 && !$flagMemberLoop && !$flagSteroid && !$flagUltraVoucher) {
                if (isset($validateSalesContactNumber->code)) {
                    throw new HttpException($validateSalesContactNumber->code, Yii::t('app', $validateSalesContactNumber->message));
                }
            }

            if (isset($tableID) && $tableID !== 0 && !$flagMemberLoop && !$flagSteroid && !$flagUltraVoucher) {
                return $validateSalesContactNumber;
            } else {
                return null;
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionDeleteSalesContactInfo()
    {
        $this->validatePost();

        $salesContactModel = new SalesContactInfo([
            'attributes' => $this->request->post('salesContactInfo'),
            'scenario' => SalesContactInfo::SCENARIO_DELETE_CONTACT
        ]);

        try {
            if (!$salesContactModel->deleteModel()) {
                throw new Exception(json_encode($salesContactModel->errors));
            }

            return true;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionFindCustomerInfo()
    {
        $this->validatePost();

        $steroidModel = new Steroid([
            'attributes' => $this->request->post()
        ]);
        try {
            return $steroidModel->fetchMemberInfo();
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionPrintQr()
    {
        $this->validatePost();

        $printModel = new PrintQRTransaction([
            'attributes' => $this->request->post()
        ]);
        $printModel->doPrint();

        if ($printModel->printResult) {
            return [
                "printDataError" => $printModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionSaveOnline()
    {
        $this->validatePost();

        $saveModel = new SaveOnlineOrder([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$saveModel->save()) {
                throw new Exception(json_encode($saveModel->errors));
            }

            return [
                'tableID' => $saveModel->salesModel->tableID,
                'salesNum' => $saveModel->salesModel->salesNum,
                'batchID' => $saveModel->batchID
            ];
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSaveNewOrder()
    {
        $this->validatePost();

        $updateModel = new UpdateOrder([
            'attributes' => $this->request->post(),
            'saveOnly' => isset($this->request->post()['saveOnly']),
            'flagScratchWin' => true
        ]);
        try {
            if (!$updateModel->preSave()) {
                $errMsg = $updateModel->errMsg;
                if ($errMsg != '') {
                    $errCode = 400;
                } else {
                    $errMsg = json_encode($updateModel->getErrors());
                    $errCode = 500;
                }
                throw new Exception($errMsg, NULL, $errCode);
            }
            return [
                'selfOrderCampaign' => $updateModel->selfOrderCampaign,
                'batchID' => $updateModel->batchID
            ];
        } catch (Exception $ex) {
            $this->returnSaveError($ex->getMessage(), $ex->getCode());
        }
    }

    public function actionSaveCampaign()
    {
        $this->validatePost();
        $saveModel = new UpdateOrder([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$saveModel->saveCampaign()) {
                throw new Exception(json_encode($saveModel->errors));
            }

            return [
                'selfOrderCampaign' => null,
                'batchID' => $saveModel->batchID
            ];
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSaveOnlineCampaign()
    {
        $this->validatePost();
        $saveModel = new SaveOnlineOrder([
            'attributes' => $this->request->post()
        ]);
        try {

            if ($saveModel->promoID) {
                if (!$saveModel->saveCampaignPromo()) {
                    throw new Exception(json_encode($saveModel->errors));
                }
            } else {
                if (!$saveModel->saveCampaign()) {
                    throw new Exception(json_encode($saveModel->errors));
                }
            }

            return [
                'tableID' => $saveModel->salesModel['tableID'],
                'salesNum' => $saveModel->salesModel['salesNum'],
                'batchID' => $saveModel->batchID
            ];
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionUpdate()
    {
        $this->validatePost();

        $updateModel = new UpdateOrder([
            'attributes' => $this->request->post()
        ]);
        try {
            // //list current Mac Address
            AppHelper::checkMacAddress();
            if (!$updateModel->preSave()) {
                $errMsg = $updateModel->errMsg;
                if ($errMsg != '') {
                    $errCode = 400;
                    if ($updateModel->hasErrors('promotionID')) {
                        $errCode = 402;
                    }
                } else {
                    $errMsg = json_encode($updateModel->getErrors());
                    $errCode = 500;
                }
                throw new Exception($errMsg, NULL, $errCode);
            }
            
            return [
                'batchID' => $updateModel->batchID,
                'autoRemovePromotion' => $updateModel->flagAutoRemovePromotion,
                'specialPriceHasExp' => $updateModel->specialPriceHasExp
            ];
        } catch (Exception $ex) {
            Logging::save($updateModel->salesNum, Logging::SAVE_ORDER_EXCEPTION, json_encode($ex->getMessage(),true));
            $this->returnSaveError($ex->getMessage(), $ex->getCode());
        }
    }

    private function runPrintOrder($updateModel) {
        if ($updateModel->batchID !== 0 || (isset($updateModel->flagFireOrderIDs) && count($updateModel->flagFireOrderIDs) > 0)) {
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne(['branchID' => $branchID]);
            $printTakeAwayAfterPayment = Setting::getValue1('POS', 'Print Take Away Order After Payment');
            $printTakeAwayChecker = Setting::getValue1('POS', 'Take Away Print Checker');
            $printTakeAwayBill = Setting::getValue1('POS', 'Take Away Print Bill');

            $printOrder = false;
            $printChecker = true;
            $printBill = true;

            $printOrderData = [];
            $printCheckerData = [];
            $printBillData = [];
            $printTwoData = [];
            $printDataAll = [];
            if ($updateModel->tableID == 0 || $branchModel->posModeID == 2) {
                $printOrder = ($printTakeAwayAfterPayment == 0);
            } else {
                $printOrder = true;
            }

            if ($updateModel->tableID == 0 && $printTakeAwayChecker == 0) {
                $printChecker = false;
            }

            if ($updateModel->batchID !== 0 && ($updateModel->tableID == 0 && $printTakeAwayBill == 0) || $updateModel->tableID > 0) {
                $printBill = false;
            }

            if ($printOrder) {
                $printData = [
                    'tableID' => $updateModel->tableID,
                    'salesNum' => $updateModel->salesNum,
                    'batchID' => $updateModel->batchID,
                    'flagFireOrderIDs' => isset($updateModel->flagFireOrderIDs) ? $updateModel->flagFireOrderIDs : []
                ];

                $printingModel = new PrintOrder([
                    'attributes' => $printData
                ]);

                $printingModel->doPrint();
                
                $printingModel->scenario = PrintChecker::SCENARIO_CANCEL_ORDER;
                $printingModel->doPrint();

                $printOrderData = AndroidPrintConnector::getData();
            }

            if ($printChecker) {
                $printData = [
                    'tableID' => $updateModel->tableID,
                    'salesNum' => $updateModel->salesNum,
                    'batchID' => $updateModel->batchID,
                    'stationID' => $updateModel->stationID,
                    'flagFireOrderIDs' => isset($updateModel->flagFireOrderIDs) ? $updateModel->flagFireOrderIDs : []
                ];

                $printingModel = new PrintChecker([
                    'attributes' => $printData
                ]);
                $printingModel->doPrint();
                
                $printingModel->scenario = PrintChecker::SCENARIO_CANCEL_ORDER;
                $printingModel->doPrint();

                $printCheckerData = AndroidPrintConnector::getData();
            }

            $printTwoData = array_merge($printOrderData, $printCheckerData);
            if ($printBill) {
                $printData = [
                    'tableID' => $updateModel->tableID,
                    'salesNum' => $updateModel->salesNum,
                    'stationID' => $updateModel->stationID
                ];
                $printingModel = new PrintBill([
                    'attributes' => $printData
                ]);
                $printingModel->doPrint();
        
                $printBillData = AndroidPrintConnector::getData();
            }

            $printDataAll = array_merge($printTwoData, $printBillData);
            if ($updateModel->tableID == 0 && $printTakeAwayChecker == 1) {
                return $printDataAll;
            } else {
                return $printTwoData;
            }
            return [];
        } else {
            return [];
        }
    }

    public function actionMerge()
    {
        $this->validatePost();

        $mergeModel = new MergeTable([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$mergeModel->save()) {
                throw new Exception(json_encode($mergeModel->errors));
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionMoveTable()
    {
        $this->validatePost();
        $moveModel = new MoveTable([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$moveModel->save()) {
                throw new Exception(json_encode($moveModel->errors));
            }

            return $moveModel->salesNum;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionMoveItem()
    {
        $this->validatePost();

        $moveModel = new MoveItem([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$moveModel->save()) {
                throw new Exception(json_encode($moveModel->errors));
            }

            $salesNums = [$moveModel['sourceSalesNum'], $moveModel['salesNum']];
            foreach ($salesNums as $salesNum) {
                $salesModel = $this->findOutstandingOrder($salesNum);
                $newSalesMenus = [];
                foreach ($salesModel['salesMenu'] as $salesMenu) {
                    $newSalesMenu = [];
                    foreach ($salesMenu as $key => $value) {
                        $newSalesMenu[$key] = $value;
                    }
                    if (!isset($newSalesMenu['menuCategoryID'])) {
                        $newSalesMenu['menuCategoryID'] = $salesMenu['menuCategoryID'];
                    }
                    if (!isset($newSalesMenu['menuCategoryDetailID'])) {
                        $newSalesMenu['menuCategoryDetailID'] = $salesMenu['menuCategoryDetailID'];
                    }
                    $flagSeparateTaxCalculation = $salesMenu['flagSeparateTaxCalculation'];
                    $newSalesMenu['menuFlagTax'] = (int) $salesMenu['flagTax'];

                    $packages = [];
                    if ($salesMenu['packages']) {
                        foreach ($salesMenu['packages'] as $package) {
                            foreach ($package as $key => $value) {
                                $newPackage[$key] = $value;
                            }
                            $newPackage['menuFlagTax'] = $flagSeparateTaxCalculation === 0 ? (int) $salesMenu['flagTax'] : (int) $package['flagTax'];
                            $packages[] = $newPackage;
                        }
                    }
                    $newSalesMenu['packages'] = $packages;

                    $extras = [];
                    if ($salesMenu['extras']) {
                        foreach ($salesMenu['extras'] as $extra) {
                            foreach ($extra as $key => $value) {
                                $newExtra[$key] = $value;
                            }
                            $newExtra['menuFlagTax'] = (int) $salesMenu['flagTax'];
                            $extras[] = $newExtra;
                        }
                    }
                    $newSalesMenu['extras'] = $extras;

                    $newSalesMenus[] = $newSalesMenu;
                }

                $salesModel['salesMenu'] = $newSalesMenus;

                /* Notes: Apply menu promo - Start */
                $newSalesModel = MoveItem::applyMenuPromo($salesModel);
                /* Notes: Apply menu promo - End */

                $updateModel = new UpdateOrder([
                    'attributes' => $newSalesModel
                ]);
                $updateModel->discountTotal = null;
                $updateModel->applySplit = 1;
                $updateModel->preSave();
            }

            return $moveModel->batchID;
        } catch (Exception $ex) {
            $this->returnSaveError($ex->getMessage());
        }
    }

    public function actionCancelTable()
    {
        $this->validatePost();

        $this->cancelTableService->cancelTable(
            $this->request->post()
        );
    }

    public function actionLink()
    {
        $this->validatePost();

        $linkModel = new LinkTable([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$linkModel->save()) {
                throw new Exception(json_encode($linkModel->errors));
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionTakeAway()
    {
        $this->validatePost();

        $bookModel = new BookTable([
            'attributes' => $this->request->post()
        ]);

        $updateModel = new UpdateOrder([
            'attributes' => $this->request->post()
        ]);
        $printData = [];
        $transaction = Yii::$app->db->beginTransaction('Serializable');
        try {
            if (!$bookModel->save()) {
                throw new Exception(json_encode($bookModel->getErrors()));
            }

            // Insert mac address
            AppHelper::checkMacAddress();
            $updateModel->salesNum = $bookModel->salesNum;
            if (!$updateModel->save()) {
                $errMsg = $updateModel->errMsg;
                if ($errMsg != '') {
                    $errCode = 400;
                } else {
                    $errMsg = json_encode($updateModel->getErrors());
                    $errCode = 500;
                }
                throw new Exception($errMsg, NULL, $errCode);
            }

            // Insert Customer Transaction POS
            $postData = $this->request->post();
            if (isset($postData['fullName']) && isset($postData['email'])) {
                $customerTransaction = new TrCustomerTransaction();
                $customerTransaction->salesNum = $bookModel->salesNum;
                $customerTransaction->fullName = $postData['fullName'];
                $customerTransaction->email = $postData['email'];
                $customerTransaction->phoneNumber = $postData['phoneNumber'] ? $postData['phoneNumber'] : null;

                if (!$customerTransaction->save()) {
                    throw new Exception(json_encode($customerTransaction->getErrors()), 500);
                }
            }

            // Insert Platform Fee Data
            if (isset($postData['platformFee']) && $postData['platformFee']) {
                $salesPlatformFeeModel = new SalesPlatformFee();
                if (!$salesPlatformFeeModel->saveModel($bookModel->salesNum, $postData['platformFee'])) {
                    throw new Exception(json_encode($salesPlatformFeeModel->errMsg), 500);
                }
            }

            $transaction->commit();
            return [
                'salesNum' => $updateModel->salesNum,
                'batchID' => $updateModel->batchID,
                'autoRemovePromotion' => $updateModel->flagAutoRemovePromotion,
                'specialPriceHasExp' => $updateModel->specialPriceHasExp
            ];
        } catch (Exception $ex) {
            if ($ex->getCode() === 0) {
                $errCode = 500;
            } else {
                $errCode = $ex->getCode();
            }
            $transaction->rollBack();
            $this->returnSaveError($ex->getMessage(), $ex->getCode());
        }
    }

    public function actionRequestBill()
    {
        $this->validatePost();

        if (!$billingPrintCount = SalesHead::updatePrintCount(
            SalesHead::PRINT_BILL,
            $this->request->post('tableID'),
            $this->request->post('salesNum'),
            false,
            true
        )) {
            throw new HttpException(
                500,
                Yii::t('app', 'Failed to update billing count')
            );
        }
        return $billingPrintCount;
    }

    public function actionPrintChecker()
    {
        $this->validatePost();

        $printingModel = new PrintChecker([
            'attributes' => $this->request->post()
        ]);
        $printingModel->doPrint();

        $printingModel->scenario = PrintChecker::SCENARIO_CANCEL_ORDER;
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintSelfChecker()
    {
        $this->validatePost();

        $printingModel = new PrintChecker([
            'attributes' => $this->request->post()
        ]);

        $printingModel->scenario = PrintChecker::SCENARIO_SELF_ORDER;
        $printingModel->doPrint();

        return AndroidPrintConnector::getData();
    }

    public function actionPrintMoveItemChecker()
    {
        $this->validatePost();

        $printingModel = new PrintChecker([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrintChecker::SCENARIO_MOVE_ITEM;
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintCancelTableChecker()
    {
        $this->validatePost();

        $printingModel = new PrintChecker([
            'attributes' => $this->request->post()
        ]);

        $printingModel->scenario = PrintChecker::SCENARIO_CANCEL_TABLE;
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintOrder()
    {
        $this->validatePost();

        $printingModel = new PrintOrder([
            'attributes' => $this->request->post()
        ]);
        $printingModel->doPrint();

        $printingModel->scenario = PrintChecker::SCENARIO_CANCEL_ORDER;
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintSelfOrder()
    {
        $this->validatePost();

        $printingModel = new PrintOrder([
            'attributes' => $this->request->post()
        ]);

        $printingModel->scenario = PrintOrder::SCENARIO_SELF_ORDER;
        $printingModel->doPrint();

        return AndroidPrintConnector::getData();
    }

    public function actionPrintMoveItemOrder()
    {
        $this->validatePost();

        $printingModel = new PrintOrder([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrintOrder::SCENARIO_MOVE_ITEM;
        $printingModel->doPrint();
        if (!$printingModel) {
            Yii::warning(json_encode($printingModel->errors));
        }

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintMoveTableOrder()
    {
        $this->validatePost();

        $printingModel = new PrintOrder([
            'attributes' => $this->request->post()
        ]);
        $printingModel->batchID = 0;
        $printingModel->scenario = PrintOrder::SCENARIO_MOVE_TABLE;
        if (!$printingModel->doPrint()) {
            Yii::warning(json_encode($printingModel->errors));
        }

        return AndroidPrintConnector::getData();
    }

    public function actionPrintAllChecker()
    {
        $this->validatePost();

        $printingModel = new PrintChecker([
            'attributes' => $this->request->post()
        ]);
        
        if ($printingModel->shouldPrintAfterPayment) {
            $printingModel->scenario = PrintChecker::SCENARIO_PRINT_CHECKER_AFTER_PAYMENT;
        }
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintBill()
    {
        $this->validatePost();

        $printingModel = new PrintBill([
            'attributes' => $this->request->post()
        ]);
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionSyncSelfOrder()
    {
        $this->validatePost();
        $ezoSettings = Setting::getEZOSetting();
        if ($ezoSettings['Activate EZO'] == 1) {
            $apiUrl = Setting::getEsoFsApiUrl();
            if ($apiUrl) {
                $syncSelfOrderModel = new SyncSelfOrder([
                    'attributes' => $this->request->post()
                ]);
                $syncSelfOrderModel->addQueue();
            }
        }
    }

    public function actionViewBill()
    {
        $this->validatePost();
        return $this->findOutstandingOrder($this->request->post('salesNum'), $this->request->post('tableID'));
    }

    private function validatePost()
    {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
    }

    private function findOutstandingOrder($salesNum = null, $tableID = null)
    {
        $model = new OutstandingOrder();
        $model->salesNum = $salesNum;
        $model->tableID = $tableID;
        return $model->get();
    }

    public function actionGetSales()
    {
        $this->validatePost();

        if ($this->request->post('salesNum')) {
            $salesHeadModel = SalesHead::find()
                ->andWhere(['salesNum' => $this->request->post('salesNum')])
                ->one();
        } else {
            $salesHeadModel = SalesHead::findOutstanding()
                ->andWhere(['tableID' => $this->request->post('tableID')])
                ->one();
        }
        if (!$salesHeadModel) {
            throw new HttpException(404, Yii::t('app', 'Order not found'));
        }

        $salesMenuModel = SalesMenu::find()
            ->andWhere(['salesNum' => $salesHeadModel->salesNum])
            ->all();

        return [
            'salesHead' => $salesHeadModel,
            'salesMenu' => $salesMenuModel
        ];
    }

    public function actionGetSalesHead()
    {
        $this->validatePost();

        $getSalesMain = new SalesMenu([
            'attributes' => $this->request->post()
        ]);

        return $getSalesMain->getDataAsArray();
    }

    public function actionGetSalesHeadChild()
    {
        $this->validatePost();

        $getSalesChild = new SalesMenu([
            'attributes' => $this->request->post()
        ]);

        $tableID = $this->request->post('tableID');

        return $getSalesChild->getDataChildAsArray($tableID);
    }

    public function actionGetSalesHeadArray()
    {
        $this->validatePost();

        $getSalesMainArray = new SalesMenu([
            'attributes' => $this->request->post()
        ]);

        return $getSalesMainArray->getDataAllAsArray();
    }

    public function actionAddSalesChild()
    {
        $this->validatePost();

        $addSalesChildModel = new AddSalesChild([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$addSalesChildModel->save()) {
                $errMsg = $addSalesChildModel->errMsg;
                if ($errMsg != '') {
                    $errCode = 400;
                } else {
                    $errMsg = json_encode($addSalesChildModel->getErrors());
                    $errCode = 500;
                }
                throw new Exception($errMsg, NULL, $errCode);
            }
            return $addSalesChildModel->salesNum;
        } catch (Exception $ex) {
            $this->returnSaveError($ex->getMessage(), $ex->getCode());
        }
    }

    public function actionAddSalesMenuChild()
    {
        $this->validatePost();

        $updateMenuSplitBillModel = new UpdateMenuSplitBill([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$updateMenuSplitBillModel->save()) {
                throw new Exception(json_encode($updateMenuSplitBillModel->errors));
            }

            $salesNums = [$updateMenuSplitBillModel['salesNumTarget'], $updateMenuSplitBillModel['sourceSalesNum']];
            foreach ($salesNums as $salesNum) {
                $salesModel = $this->findOutstandingOrder($salesNum);
                $newSalesMenus = [];
                foreach ($salesModel['salesMenu'] as $salesMenu) {
                    $newSalesMenu = [];
                    $newSalesMenu['menuCategoryID'] = $salesMenu['menuCategoryID'];
                    $newSalesMenu['menuCategoryDetailID'] = $salesMenu['menuCategoryDetailID'];
                    $newSalesMenu['menuFlagTax'] = (int) $salesMenu['flagTax'];
                    $flagSeparateTaxCalculation = $salesMenu['flagSeparateTaxCalculation'];
                    foreach ($salesMenu as $key => $value) {
                        $newSalesMenu[$key] = $value;
                    }

                    $packages = [];
                    if ($salesMenu['packages']) {
                        foreach ($salesMenu['packages'] as $package) {
                            foreach ($package as $key => $value) {
                                $newPackage[$key] = $value;
                            }
                            $newPackage['menuFlagTax'] = $flagSeparateTaxCalculation === 0 ? (int) $salesMenu['flagTax'] : (int) $package['flagTax'];
                            $packages[] = $newPackage;
                        }
                    }
                    $newSalesMenu['packages'] = $packages;

                    $extras = [];
                    if ($salesMenu['extras']) {
                        foreach ($salesMenu['extras'] as $extra) {
                            foreach ($extra as $key => $value) {
                                $newExtra[$key] = $value;
                            }
                            $newExtra['menuFlagTax'] = (int) $salesMenu['flagTax'];
                            $extras[] = $newExtra;
                        }
                    }
                    $newSalesMenu['extras'] = $extras;

                    $newSalesMenus[] = $newSalesMenu;
                }

                $salesModel['salesMenu'] = $newSalesMenus;
                //to force calculate discount
                unset($salesModel['discountTotal']);
                $updateModel = new UpdateOrder([
                    'attributes' => $salesModel
                ]);
                $updateModel->applySplit = 1;
                $updateModel->preSave();

                try {
                    // Sync self order
                    $ezoSettings = Setting::getEZOSetting();
                    if ($ezoSettings['Activate EZO'] == 1) {
                        $apiUrl = Setting::getEsoFsApiUrl();
                        if ($apiUrl) {
                            $syncSelfOrderModel = new SyncSelfOrder();
                            $syncSelfOrderModel->refNum = $updateModel->salesNum;
                            $syncSelfOrderModel->type = 'salesNum';
                            $syncSelfOrderModel->addQueue();
                        }
                    }
                } catch (\Throwable $th) {
                    Yii::error($th);
                }
                
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionCheckSplitBill()
    {
        $this->validatePost();

        $checkSplitBillModel = new CheckSplitBill([
            'attributes' => $this->request->post()
        ]);

        try {
            $result = $checkSplitBillModel->hasSplitBill();
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionDeleteSalesChild()
    {
        $this->validatePost();

        $deleteSalesChildModel = new DeleteSalesChild([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$deleteSalesChildModel->save()) {
                throw new Exception(json_encode($deleteSalesChildModel->errors));
            }
            
            UpdateOrder::reCalculateWhenRemoveSplit($deleteSalesChildModel->salesNumHead);
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionDeleteSalesMenuChild()
    {
        $this->validatePost();

        $deleteSalesMenuChildModel = new DeleteSalesMenuChild([
            'attributes' => $this->request->post()
        ]);

        try {
            $salesMenuID = $this->request->post('salesMenuID');
            $salesMenuModelChild = SalesMenu::find()
                ->innerJoinWith("menu")
                ->where(['ID' => $salesMenuID])->one();
            $childSalesNum = $salesMenuModelChild ? $salesMenuModelChild->salesNum : '';

            if (!$deleteSalesMenuChildModel->save()) {
                throw new Exception(json_encode($deleteSalesMenuChildModel->errors));
            }

            $salesNums = [];
            if ($childSalesNum != '') {
                $salesNums[] =  $childSalesNum;
                $exploded = explode("-", $childSalesNum);
                $headSalesNum = $exploded[0];
                $salesNums[] = $headSalesNum;
            }

            foreach ($salesNums as $salesNum) {
                $salesModel = $this->findOutstandingOrder($salesNum);
                $newSalesMenus = [];
                foreach ($salesModel['salesMenu'] as $salesMenu) {
                    $newSalesMenu = [];
                    foreach ($salesMenu as $key => $value) {
                        $newSalesMenu[$key] = $value;
                    }
                    $flagSeparateTaxCalculation = $salesMenu['flagSeparateTaxCalculation'];
                    $newSalesMenu['menuFlagTax'] = (int) $salesMenu['flagTax'];

                    $packages = [];
                    if ($salesMenu['packages']) {
                        foreach ($salesMenu['packages'] as $package) {
                            foreach ($package as $key => $value) {
                                $newPackage[$key] = $value;
                            }
                            $newPackage['menuFlagTax'] = $flagSeparateTaxCalculation === 0 ? (int) $salesMenu['flagTax'] : (int) $package['flagTax'];
                            $packages[] = $newPackage;
                        }
                    }
                    $newSalesMenu['packages'] = $packages;

                    $extras = [];
                    if ($salesMenu['extras']) {
                        foreach ($salesMenu['extras'] as $extra) {
                            foreach ($extra as $key => $value) {
                                $newExtra[$key] = $value;
                            }
                            $newExtra['menuFlagTax'] = (int) $salesMenu['flagTax'];
                            $extras[] = $newExtra;
                        }
                    }
                    $newSalesMenu['extras'] = $extras;

                    $newSalesMenus[] = $newSalesMenu;
                }

                $salesModel['salesMenu'] = $newSalesMenus;
                $updateModel = new UpdateOrder([
                    'attributes' => $salesModel
                ]);
                $updateModel->applySplit = 1;
                $updateModel->preSave();

                try {
                    // Sync self order
                    $ezoSettings = Setting::getEZOSetting();
                    if ($ezoSettings['Activate EZO'] == 1) {
                        $apiUrl = Setting::getEsoFsApiUrl();
                        if ($apiUrl) {
                            $syncSelfOrderModel = new SyncSelfOrder();
                            $syncSelfOrderModel->refNum = $updateModel->salesNum;
                            $syncSelfOrderModel->type = 'salesNum';
                            $syncSelfOrderModel->addQueue();
                        }
                    }
                } catch (\Throwable $th) {
                    Yii::error($th);
                }
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    private function getSalesHeadData($salesModel)
    {
        return [
            'salesNum' => $salesModel->salesNum,
            'salesDateOut' => $salesModel->salesDateOut,
            'tableID' => $salesModel->tableID,
            'tableName' => $salesModel->table ? $salesModel->table->tableName : 'Quick Service',
            'paxTotal' => $salesModel->paxTotal,
            'subtotal' => (float) $salesModel->subtotal,
            'discountTotal' => (float) $salesModel->discountTotal,
            'menuDiscountTotal' => (float) $salesModel->menuDiscountTotal,
            'promotionDiscount' => (float) $salesModel->promotionDiscount,
            'otherTaxTotal' => (float) $salesModel->otherTaxTotal,
            'vatTotal' => (float) $salesModel->vatTotal,
            'grandTotal' => (float) $salesModel->grandTotal,
            'roundingTotal' => (float) $salesModel->roundingTotal,
            'billingPrintCount' => $salesModel->billingPrintCount,
            'totalLoaded' => true,
            'visitPurposeID' => $salesModel->visitPurposeID,
            'voucherTotal' => $salesModel->voucherTotal,
            'billSalesMenu' => []
        ];
    }

    private function getSalesMenuData($salesMenu)
    {
        return [
            'ID' => $salesMenu->ID,
            'localID' => $salesMenu->localID,
            'batchID' => $salesMenu->batchID,
            'menuID' => $salesMenu->menuID,
            'menuName' => $salesMenu->menu->menuName,
            'menuGroupID' => $salesMenu->menuGroupID,
            'qty' => (int) $salesMenu->qty,
            'originalPrice' => (float) $salesMenu->originalPrice,
            'price' => (float) $salesMenu->price,
            'notes' => $salesMenu->notes,
            'statusID' => $salesMenu->statusID,
            'statusName' => $salesMenu->status->statusName
        ];
    }

    public function actionSetRenameBill()
    {
        $this->validatePost();

        $renameBillModel = new UpdateMenuSplitBill([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$renameBillModel->setRenameBill()) {
                throw new Exception(json_encode($renameBillModel->errors));
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }

        return $renameBillModel->additionalInfo;
    }

    public function actionUpdateVisitPurpose()
    {
        $this->validatePost();

        $updateVisitPurposeModel = new UpdateOrder([
            'attributes' => $this->request->post()
        ]);
        return $updateVisitPurposeModel->updateVisitPurpose();
    }

    public function actionSalesOrderList()
    {
        $token = null;
        if ($this->request->headers->get('authorization')) {
            $token = str_replace(
                'Bearer ',
                '',
                $this->request->headers->get('authorization')
            );
        }

        return SalesHead::findOutstandingOrderListAsArray($token);
    }

    public function actionBillTime()
    {
        $this->validatePost();

        $billTimeModel = new Table([
            'attributes' => $this->request->post()
        ]);
        return $billTimeModel->getBillTime($this->request->post('tableID'));
    }

    public function actionPendingOrder()
    {
        return OrderCompletion::getCountOutstandingOrder();
    }

    public function actionGenerateQrisQrCode()
    {
        $paymentMethodTypeModel = PaymentMethod::findActive()
            ->innerJoinWith('posExternalPayment')
            ->andWhere(['IN', PosExternalPayment::tableName() . '.posExternalPaymentID', ['qris', 'qrisyukk', 'qrisshopee', 'qrisnobu', 'qrisesb', 'qrisotopay', 'qrisgpay', 'qrisbri', 'qrisdki']])
            ->one();

        if ($paymentMethodTypeModel) {
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne($branchID);
            $model = new ExternalPaymentMethod([
                'attributes' => $this->request->post()
            ]);
            $model->branchID = $branchID;
            $model->paymentMethod = $paymentMethodTypeModel->posExternalPaymentID;
            $model->paymentMethodID = $paymentMethodTypeModel->paymentMethodID;
            $model->salesNum = $branchModel->branchCode . round(microtime(true) * 1000);

            if (!$result = $model->generateQrisQrCode()) {
                Yii::error($model->errors);
                throw new ServerErrorHttpException(json_encode($model->errors));
            }
            return $result;
        } else {
            return false;
        }
    }

    public function actionGenerateQrisQrCodeDynamic()
    {
        $externalPaymentModel = new PosExternalPayment([
            'attributes' => $this->request->post()
        ]);

        if(!$externalPaymentModel->posExternalPaymentID) {
            return false;
        }

        $paymentMethodTypeModel = PaymentMethod::findActive()
            ->innerJoinWith('posExternalPayment')
            ->andWhere([PosExternalPayment::tableName() . '.posExternalPaymentID' => $externalPaymentModel->posExternalPaymentID])
            ->one();

        if ($paymentMethodTypeModel) {
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne($branchID);
            $model = new ExternalPaymentMethod([
                'attributes' => $this->request->post()
            ]);
            $model->branchID = $branchID;
            $model->paymentMethod = $paymentMethodTypeModel->posExternalPaymentID;
            $model->paymentMethodID = $paymentMethodTypeModel->paymentMethodID;
            $model->salesNum = $branchModel->branchCode . round(microtime(true) * 1000);

            if (!$result = $model->generateQrisQrCode()) {
                Yii::error($model->errors);
                throw new ServerErrorHttpException(json_encode($model->errors));
            }
            return $result;
        } else {
            return false;
        }
    }

    public function actionSubmitQrisPayment()
    {
        ini_set('memory_limit', '-1');
        $paymentMethodTypeModel = PaymentMethod::findActive()
            ->andWhere(['IN', PosExternalPayment::tableName() . '.posExternalPaymentID', ['qris', 'qrisyukk', 'qrisshopee', 'qrisnobu', 'qrisesb', 'qrisotopay', 'qrisgpay', 'qrisbri', 'qrisdki']])
            ->one();

        if ($paymentMethodTypeModel) {
            $model = new SelfOrderTakeAway([
                'attributes' => $this->request->post()['data']
            ]);
            $model->externalApi = 1;
            $model->paymentMethodCode = $paymentMethodTypeModel->paymentMethodCode;
            if (!$result = $model->save()) {
                throw new BadRequestHttpException(json_encode($model->errors));
            }

            $result['queueNum'] = SelfOrderTakeAway::defineQueueNumKiosk($result['salesNum'], $result['queueNum']);

            $printData = AndroidPrintConnector::getData();
            $result['printData'] = $printData;

            return $result;
        } else {
            return false;
        }
    }

    public function actionSubmitQrisPaymentDynamic()
    {
        ini_set('memory_limit', '-1');
        $model = new SelfOrderTakeAway([
            'attributes' => $this->request->post()['data']
        ]);
       
        if(!$model->paymentMethod) {
            return false;
        }

        $paymentMethodTypeModel = PaymentMethod::findActive()
            ->andWhere([PaymentMethod::tableName() . '.posExternalPaymentID' => $model->paymentMethod])
            ->one();

        if ($paymentMethodTypeModel) {
            $model->externalApi = 1;
            $model->paymentMethodCode = $paymentMethodTypeModel->paymentMethodCode;
            if (!$result = $model->save()) {
                throw new BadRequestHttpException(json_encode($model->errors));
            }

            $result['queueNum'] = SelfOrderTakeAway::defineQueueNumKiosk($result['salesNum'], $result['queueNum']);

            $printData = AndroidPrintConnector::getData();
            $result['printData'] = $printData;

            // @notes logging external online payment
            $modelExternalpaymentLog = new PaymentOnlineTrackingLog();
            $modelExternalpaymentLog->checkOnlinePaymentTrackingLogKiosk($model);

            return $result;
        } else {
            return false;
        }
    }

    public function actionSubmitEdcPayment()
    {
        ini_set('memory_limit', '-1');
        $model = new SelfOrderTakeAway([
            'attributes' => $this->request->post()['data']
        ]);

        if(!$model->paymentMethod) {
            return false;
        }

        $paymentMethodTypeModel = PaymentMethod::findActive()
            ->andWhere([PaymentMethod::tableName() . '.posExternalPaymentID' => $model->paymentMethod])
            ->one();

        if ($paymentMethodTypeModel) {
            $model->externalApi = 1;
            $model->flagEdcPayment = true;
            $model->paymentMethodCode = $paymentMethodTypeModel->paymentMethodCode;
            if (!$result = $model->save()) {
                throw new BadRequestHttpException(json_encode($model->errors));
            }

            $result['queueNum'] = SelfOrderTakeAway::defineQueueNumKiosk($result['salesNum'], $result['queueNum']);

            $printData = AndroidPrintConnector::getData();
            $result['printData'] = $printData;

            // @notes logging external online payment
            $modelExternalpaymentLog = new PaymentOnlineTrackingLog();
            $modelExternalpaymentLog->checkOnlinePaymentTrackingLogKiosk($model);

            return $result;
        } else {
            return false;
        }
    }

    public function actionSubmitVoucherPayment()
    {
        ini_set('memory_limit', '-1');
        $model = new SelfOrderTakeAway([
            'attributes' => $this->request->post()['data']
        ]);

        $paymentMethodTypeModel = PaymentMethod::findActive()
            ->andWhere([PaymentMethod::tableName() . '.voucherSourceID' => 1])
            ->andWhere([PaymentMethod::tableName() . '.paymentMethodTypeID' => 4])
            ->orderBy('paymentMethodID DESC')
            ->one();

        if ($paymentMethodTypeModel) {

            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne($branchID);

            $model->externalApi = 1;
            $model->flagVoucherPayment = true;
            $model->paymentMethodCode = $paymentMethodTypeModel->paymentMethodCode;
            $model->orderID = $branchModel->branchCode . round(microtime(true) * 1000);
            $model->paymentMethod = $paymentMethodTypeModel->paymentMethodID;
            
            if (!$result = $model->save()) {
                throw new BadRequestHttpException(json_encode($model->errors));
            }

            $result['queueNum'] = SelfOrderTakeAway::defineQueueNumKiosk($result['salesNum'], $result['queueNum']);

            $printData = AndroidPrintConnector::getData();
            $result['printData'] = $printData;

            // @notes logging external online payment
            $modelExternalpaymentLog = new PaymentOnlineTrackingLog();
            $modelExternalpaymentLog->checkOnlinePaymentVoucherTrackingLogKiosk($model);

            return $result;
        } else {
            return false;
        }
    }


    public function actionCalculateTotal()
    {
        $model = new CalculateTotal([
            'attributes' => Yii::$app->request->post()
        ]);

        try {
            if (!$result = $model->calculate()) {
                throw new Exception(json_encode($model->errors));
            } else {
                $return = [
                    'flagInclusive' => $model->flagInclusive,
                    'subtotal' => $model->subtotal,
                    'deliveryCost' => $model->deliveryCost,
                    'otherTaxTotal' => $model->otherTaxTotal,
                    'taxTotal' => $model->taxTotal,
                    'grandTotal' => $model->grandTotal,
                    'roundingTotal' => $model->roundingTotal,
                    'minimumOrderTotal' => $model->minimumOrderTotal,
                    'paymentValidation' => $model->paymentValidation,
                    'salesMenus' => $model->salesMenus,
                    'paymentVoucherTotal' => $model->paymentVoucherTotal,
                    'voucherDiscountTotal' => $model->voucherDiscountTotal,
                    'vouchers' => $model->vouchers,
                    'orderPayment' => $model->orderPayment,
                    'orderVoucherUsage' => $model->orderVoucherUsage,
                    'promotionID' => $model->promotionID,
                    'promotionCode' => $model->promotionCode,
                    'promotionDiscount' => $model->promotionDiscount,
                    'discountTotal' => $model->discountTotal,
                    'currentOrder' => $model->currentOrder
                ];

                return $return;
            }
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionSaveQuestionAnswer() {
        $model = new QuestionAnswer([
            'attributes' => Yii::$app->request->post()
        ]);

        try {
            if (!$result = $model->saveModel()) {
                throw new Exception(json_encode($model->errors));
            }
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }
    
    public function actionGetSalesHoldOrder() {
        return SalesHead::findHoldOrder();
    }

    public function actionGetPickupOrder() 
    {
        return SalesHead::findPickupOrder();
    }

    public function actionItemHoldOnLinkSales() {
        $this->validatePost();
        $salesNum = $this->request->post('salesNum');

        return SalesMenu::findLinkSalesHeadsHold($salesNum);
    }

    public function actionSaveTerminal() {
        $terminalModel = new Terminal([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$result = $terminalModel->saveTerminal()) {
                throw new Exception(json_encode($terminalModel->errors));
            }
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }


    private function returnSaveError($message, $code = 500)
    {
        throw new HttpException($code, $message);
    }

    public function actionGetActiveSalesmenu() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }
        return SalesMenu::findActiveSalesmenu($this->request->post('salesNum'));
    }

    public function actionGetActiveSaleslink() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }
        return SalesMenu::findActiveSaleslink($this->request->post('salesNum'));
    }

    public function actionValidateStockSalesMenu() {
        $salesNum = $this->request->post('salesNum');
        $salesMenu = $this->request->post('salesMenu');
        $transactionModeID = $this->request->post('transactionModeID');

        $menuSoldOutArray = [];
        foreach ($salesMenu as $sm) {
          $validateStockModel = new ValidateStock();
          $validateStockModel->salesNum = $salesNum;
          $validateStockModel->menuID = $sm['menuID'];
          $validateStockModel->qty = $sm['qty'];
          $validateStockModel->transactionModeID = $transactionModeID;

          $menuName = $validateStockModel->validateStockOnBranchMenu();
          if ($menuName) {
              $menuSoldOutArray[] = [
                'menuID' => $sm['menuID'],
                'menuName' => $menuName
              ];
          }

          if (isset($sm['packages'])) {
            foreach ($sm['packages'] as $pck) {
              $validateStockModel = new ValidateStock();
              $validateStockModel->salesNum = $salesNum;
              $validateStockModel->menuID = $pck['menuID'];
              $validateStockModel->qty = $pck['qty'] * $sm['qty'];
              $validateStockModel->transactionModeID = $transactionModeID;
  
              $menuName = $validateStockModel->validateStockOnBranchMenu();
              if ($menuName) {
                  $menuSoldOutArray[] = [
                    'menuID' => $pck['menuID'],
                    'menuName' => $menuName
                  ];
              }
            }
          }

          if (isset($sm['extras'])) {
            foreach ($sm['extras'] as $ext) {
              $menuExtraModel = MenuExtra::find()
                  ->with('menu')
                  ->where(['=', 'ms_menuextra.menuExtraID', $ext['menuExtraID']])
                  ->one();

              if ($menuExtraModel->menu) {
                $validateStockModel = new ValidateStock();
                $validateStockModel->salesNum = $salesNum;
                $validateStockModel->menuID = $menuExtraModel->menu->menuID;
                $validateStockModel->qty = $ext['qty'] * $sm['qty'];
                $validateStockModel->transactionModeID = $transactionModeID;
    
                $menuName = $validateStockModel->validateStockOnBranchMenu();
                if ($menuName) {
                    $menuSoldOutArray[] = [
                      'menuID' => $ext['menuExtraID'],
                      'menuName' => $menuName
                    ];
                }
              }
            }
          }
        }

        return $menuSoldOutArray;
    }
}
