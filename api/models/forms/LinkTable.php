<?php
namespace app\models\forms;

use app\models\BrandSetting;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property int $tableID
 * @property array $salesLink
 * 
 * PRIVATE
 * @property SalesHead $mainSalesModel
 */
class LinkTable extends Model {
    public $tableID;
    public $salesNum;
    public $salesLink;
    public $mainSalesModel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum'], 'required'],
            [['salesNum'], 'string'],
            [['salesLink'], 'safe'],
            [['salesNum'], 'validateTable']
        ];
    }

    public function validateTable($attribute) {
        $this->mainSalesModel = SalesHead::findMainSales($this->tableID, $this->salesNum);
        if (!$this->mainSalesModel) {
            $this->addError($attribute, 'Invalid table ID');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $salesNums = [];
        $transaction = Yii::$app->db->beginTransaction();
        try {

            $externalMemberSalesNum = [];
            for ($i=0; $i < count($this->salesLink); $i++) {
                $salesLink = $this->salesLink[$i];
                if (isset($salesLink['isExternalMemberLoyalty']) && $salesLink['isExternalMemberLoyalty'] === true) {
                    array_push($externalMemberSalesNum, $salesLink['salesNum']);
                }
            }

            if (count($externalMemberSalesNum) > 1) {
                $externalMemberSetting = BrandSetting::getExternalMemberSetting();
                $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
                $externalMemberSalesModel = SalesHead::find()
                    ->innerJoinWith('activeMainSalesMenus')
                    ->where(['IN', SalesHead::tableName() . '.salesNum', $externalMemberSalesNum])
                    ->andWhere([SalesHead::tableName() . '.externalMembershipTypeID' => $membershipType])
                    ->all();

                if ($externalMemberSalesModel) {
                    $duplicatePromoVoucherCodeHead = [];
                    $duplicatePromoVoucherCodeItem = [];
                    $isDetectedDuplicate = false;
                    foreach ($externalMemberSalesModel as $sales) {
                        // check promotion voucher head
                        if (!in_array($sales->promotionVoucherCode, $duplicatePromoVoucherCodeHead)) {
                            if ($sales->promotionVoucherCode && $sales->promotionVoucherCode != '') {
                                array_push($duplicatePromoVoucherCodeHead, $sales->promotionVoucherCode);
                            }
                        } else {
                            $isDetectedDuplicate = true;
                            break;
                        }

                        // check promotion voucher item
                        foreach ($sales->activeMainSalesMenus as $salesMenu) {
                            if (!in_array($salesMenu->promotionVoucherCode, $duplicatePromoVoucherCodeItem)) {
                                if ($salesMenu->promotionVoucherCode && $salesMenu->promotionVoucherCode != '') {
                                    array_push($duplicatePromoVoucherCodeItem, $salesMenu->promotionVoucherCode);
                                }
                            } else {
                                $isDetectedDuplicate = true;
                                break;
                            }
                        }
                    }

                    if ($isDetectedDuplicate) {
                        throw new Exception('Duplicate voucher detected');
                    }
                }
            }

            $printEsoFsQr = $this->mainSalesModel->printEsoFsQr;
            // @notes cek jika child sales pakai eso fs maka sync ke cloud
            if ($this->mainSalesModel->printEsoFsQr == 0) {
                $salesLinks = [];
                foreach ($this->salesLink as $salesLink) {
                    $salesLinks[] = $salesLink['salesNum'];
                }
                if (!empty($salesLinks)) {
                    $findSalesHeadEsoQrFs = SalesHead::find()
                        ->where(['printEsoFsQr' => 1])
                        ->andWhere(['IN', 'salesNum', $salesLinks])
                        ->exists();

                    if ($findSalesHeadEsoQrFs) {
                        $printEsoFsQr = 1;
                    }
                }
            }

            $salesNums[] = $this->mainSalesModel->salesNum;
            for ($i = 1; $i < count($this->salesLink); $i++) {
                $salesLink = $this->salesLink[$i];

                $linkModel = SalesLink::find()
                    ->andWhere(['salesNum' => $this->mainSalesModel->salesNum])
                    ->andWhere(['linkSalesNum' => $salesLink['salesNum']])
                    ->one();
                if ($linkModel) {
                    $salesNums[] = $linkModel->linkSalesNum;
                } else {
                    $salesModel = SalesHead::findOutstanding()
                        ->andWhere(['salesNum' => $salesLink['salesNum']])
                        ->andWhere(['tableID' => $salesLink['tableID']])
                        ->one();
                    if (!$salesModel) {
                        throw new Exception('Invalid link table ' . $salesLink['tableID']);
                    }

                    $newLinkModel = new SalesLink();
                    $newLinkModel->salesNum = $this->mainSalesModel->salesNum;
                    $newLinkModel->linkSalesNum = $salesLink['salesNum'];
                    if (!$newLinkModel->save()) {
                        throw new Exception('Failed to save link table');
                    }
                    $salesNums[] = $newLinkModel->linkSalesNum;
                }
            }

            // @Notes: delete all unselected tables
            SalesLink::deleteAll(['AND',
                ['NOT IN', 'linkSalesNum', $salesNums],
                ['salesNum' => $this->mainSalesModel->salesNum]
            ]);

            SalesHead::updateAll([
                'billingPrintCount' => 0,
                'printEsoFsQr' => $printEsoFsQr,
                'syncDate' => null
                ], ['IN', 'salesNum', $salesNums]
            );

            // @notes: issue parent tidak pakai eso dan child naik pakai eso.
            if ($this->mainSalesModel->printEsoFsQr == 0 && $printEsoFsQr == 1) {
                SalesHead::updateAll([
                    'printEsoFsQr' => $printEsoFsQr,
                    ], ['=', 'salesNum', $this->mainSalesModel->salesNum]
                );
                $salesNums[] = $this->mainSalesModel->salesNum;
            }

            $EZOSetting = Setting::getEZOSetting();
            $activateEzo = isset($EZOSetting['Activate EZO']) && $EZOSetting['Activate EZO'] == 1;
            if ($printEsoFsQr == 1 && $activateEzo) {
                $apiUrl = Setting::getEsoFsApiUrl();
                foreach ($salesNums as $salesNum) {
                    if ($apiUrl) {
                        $syncSelfOrderModel = new SyncSelfOrder();
                        $syncSelfOrderModel->refNum = $salesNum;
                        $syncSelfOrderModel->type = 'salesNum';
                        $syncSelfOrderModel->addQueue();
                    }
                }
            }

            Logging::save($this->mainSalesModel->salesNum, Logging::LINK_TABLE,
                $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesLink', $ex->getMessage());
            return false;
        }
    }

}
