<?php
namespace app\modules\v1\controllers;

use app\models\forms\ExternalVoucher;
use app\models\Setting;
use app\models\Voucher;
use Yii;
use yii\web\HttpException;

class VoucherController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }
    
    public function actionIndex() {
        $branchID = Setting::getCurrentBranch();
        $settings = Setting::getPrintingSettings();
        $purchaseVoucher = 'offline';
        if (array_key_exists('Voucher Management', $settings)) {
            $purchaseVoucher = $settings['Voucher Management'];
        }

        $responseData = [];
        if ($purchaseVoucher == 'offline') {
            $responseData = Voucher::findNotActive()
                ->andWhere(['createdBranchID' => $branchID])
                ->all();
        } else if ($purchaseVoucher == 'online') {
            $responseData = Voucher::getOnlineVoucherList();
        }
        
        $response = [
            "voucherType" => ucwords($purchaseVoucher),
            "voucher" => $responseData
        ];

        return $response;
    }

    public function actionValidate() {
        if (!$this->request->post('voucherID') || !$this->request->post('subtotal')) {
            throw new HttpException(400);
        }
        
        $salesNum = $this->request->post('salesNum') ?: "";
        
        $voucherValidation = Voucher::validateID(
            $this->request->post('voucherID'),
            $this->request->post('subtotal'),
            $salesNum
        );
        
        if (!$voucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $voucherValidation['message']));
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidatePromotionVoucher() {
        $invalidParameter = false;
        if (!$this->request->post('voucherCode') || !$this->request->post('voucherSourceID')) {
            $invalidParameter = true;
        } else {
            if ($this->request->post('voucherSourceID') == 1) {
                if (!$this->request->post('promotionMasterCode')) {
                    $invalidParameter = true;
                }
            } else if ($this->request->post('voucherSourceID') == 7){
                if (!$this->request->post('salesNum') || !$this->request->post('promotionID')) {
                    $invalidParameter = true;
                }
            } else {
                throw new HttpException(400, 'Invalid Voucher Source');
            }
        }

        if ($invalidParameter) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        $promotionVoucherValidation = null;
        switch ($this->request->post('voucherSourceID')) {
            case 1: //ESB Online Voucher
                $promotionVoucherValidation = Voucher::validateVoucherFreeItem(
                    $this->request->post('voucherCode'),
                    $this->request->post('promotionMasterCode')
                );

                if (!$promotionVoucherValidation['status']) {
                    throw new HttpException(400, Yii::t('app', $promotionVoucherValidation['message']));
                }
                break;
            case 7: //Giftee Voucher
                $promotionVoucherValidation = ExternalVoucher::validateVoucherGiftee(
                    $this->request->post('voucherCode'),
                    null,
                    $this->request->post('salesNum'),
                    $this->request->post('promotionID')
                );

                if (!$promotionVoucherValidation['voucher']) {
                    throw new HttpException(404, Yii::t('app', $promotionVoucherValidation['status']));
                }

                $promotionVoucherValidation['voucher']->voucherName = "Giftee Voucher Free Item";
                break;
        }
        return $promotionVoucherValidation['voucher'];
    }

    public function actionValidateExternalVoucher() {
        if (!$this->request->post('voucherCode') || !$this->request->post('salesNum')) {
            throw new HttpException(400, 'Invalid Parameter');
        }
        $terminalID = $this->request->post('terminalID') ? $this->request->post('terminalID') : 'ESB';
        $voucherValidation = ExternalVoucher::validateVoucher($this->request->post('voucherCode'),
                $this->request->post('salesNum'), $terminalID);
        if (!$voucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $voucherValidation['status']));
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidateExternalVoucherMemberId() {
        if (!$this->request->post('voucherCode') || !$this->request->post('salesNum')) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        $voucherValidation = ExternalVoucher::validateVoucherMemberID($this->request->post('voucherCode'),
                $this->request->post('salesNum'));
        if (!$voucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $voucherValidation['status']));
        }

        return $voucherValidation['voucher'];
    }
    
    public function actionValidateExternalVoucherLoyalty() {
        if (!$this->request->post('voucherCode') || !$this->request->post('salesNum')) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        $voucherValidation = ExternalVoucher::validateVoucherLoyalty($this->request->post('voucherCode'),
                $this->request->post('salesNum'), true);
        if (!isset($checkVoucher['voucher']) && !$voucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $voucherValidation['status']));
        }

        return $voucherValidation['voucher'];
    }
    
    public function actionValidateExternalVoucherTada() {
        if (!$this->request->post('voucherCode')) {
            throw new HttpException(400, 'Invalid Parameter');
        }
        $voucherValidation = ExternalVoucher::validateVoucherTada($this->request->post('voucherCode'),
            $this->request->post('salesNum'));
        if (!$voucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $voucherValidation['status']));
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidateExternalVoucherGiftee() {
        if (!$this->request->post('voucherCode') || !$this->request->post('paymentMethodID')) {
            throw new HttpException(400, 'Invalid Parameter');
        }
        $voucherValidation = ExternalVoucher::validateVoucherGiftee(
            $this->request->post('voucherCode'),
            $this->request->post('paymentMethodID'), 
            $this->request->post('salesNum')
        );
        if (!$voucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $voucherValidation['status']));
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidatePromotionVoucherGiftee() {
        if (!$this->request->post('voucherCode') || !$this->request->post('promotionID')) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        $promotionVoucherValidation = ExternalVoucher::validateVoucherGiftee(
            $this->request->post('voucherCode'), null,
            $this->request->post('salesNum'),
            $this->request->post('promotionID')
        );

        if (!$promotionVoucherValidation['voucher']) {
            throw new HttpException(404,
            Yii::t('app', $promotionVoucherValidation['status']));
        }

        return $promotionVoucherValidation['voucher'];
    }

    public function actionValidateExternalVoucherCapillary() {
        if (!$this->request->post('voucherCode') || !$this->request->post('salesNum')) {
            throw new HttpException(400, 'Invalid Parameter');
        }
        $voucherValidation = ExternalVoucher::validateVoucherCapillary(
            $this->request->post('voucherCode'), $this->request->post('salesNum')
        );

        if (!$voucherValidation['voucher']) {
            if (in_array($voucherValidation['code'], [404, 400])) {
                throw new HttpException($voucherValidation['code'], Yii::t('app', $voucherValidation['status']));
            } else {
                throw new HttpException(500, Yii::t('app', $voucherValidation['status']));
            }
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidateExternalVoucherCapillaryV2() {
        if (!$this->request->post('voucherCode') || !$this->request->post('salesNum')) {
            throw new HttpException(400, 'Invalid Parameter');
        }
        $voucherValidation = ExternalVoucher::validateVoucherCapillaryV2(
            $this->request->post('voucherCode'), $this->request->post('salesNum')
        );

        if (!$voucherValidation['voucher']) {
            if (in_array($voucherValidation['code'], [404, 400])) {
                throw new HttpException($voucherValidation['code'], Yii::t('app', $voucherValidation['status']));
            } else {
                throw new HttpException(500, Yii::t('app', $voucherValidation['status']));
            }
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidateExternalVoucherQwikcilver() {
        if (!$this->request->post('voucherCode')) {
            throw new HttpException(400, "Voucher Code is required");
        }

        if ($this->request->post('trackData') == null && !$this->request->post('voucherPIN')) {
            throw new HttpException(400, "Voucher PIN is required");
        }
        
        $voucherValidation = ExternalVoucher::validateVoucherQwikCilver(
            $this->request->post('voucherCode'), $this->request->post('voucherPIN'), $this->request->post('trackData')
        );

        if (!$voucherValidation['voucher']) {
            if (in_array($voucherValidation['code'], [404, 400])) {
                throw new HttpException($voucherValidation['code'], Yii::t('app', $voucherValidation['status']));
            } else {
                throw new HttpException(500, Yii::t('app', $voucherValidation['status']));
            }
        }

        return $voucherValidation['voucher'];
    }

    public function actionValidateExternalVoucherGlobaltix() {
        if (!$this->request->post('voucherCode') || !$this->request->post('paymentMethodID')) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        $voucherValidation = ExternalVoucher::validateVoucherGlobalTix($this->request->post('voucherCode'), $this->request->post('paymentMethodID'));
        if (isset($voucherValidation->responseCode)) {
            throw new HttpException($voucherValidation->responseCode, $voucherValidation->message);
        }

        if (isset($voucherValidation->voucher)) {
            return $voucherValidation->voucher;
        }
    }
    
    public function actionValidateEsbVoucher() {
        if (
            !$this->request->post('currentVoucherID')
            || !$this->request->post('voucherIDs')
            || !$this->request->post('salesPaymentTotal')
            || !$this->request->post('salesNum')
        ) {
            throw new HttpException(400);
        }

        $voucherValidation = Voucher::validateEsbVoucher(
            $this->request->post('currentVoucherID'),
            $this->request->post('voucherIDs'),
            $this->request->post('salesPaymentTotal'),
            $this->request->post('salesNum')
        );
        if (!$voucherValidation['voucher']) {
            Yii::warning($voucherValidation);
            throw new HttpException($voucherValidation['code'],
            Yii::t('app', $voucherValidation['status']));
        }
        return $voucherValidation['voucher'];
    }

    public function actionLockingVoucher() {
        if (!$this->request->post('voucherID') || !$this->request->post('salesNum')) {
            throw new HttpException(400);
        }

        $lockingVoucher = Voucher::lockingVoucher($this->request->post('voucherID'),
            $this->request->post('salesNum'));
        if (!$lockingVoucher['status']) {
            throw new HttpException(404,
            Yii::t('app', $lockingVoucher['message']));
        }
        return $lockingVoucher['message'];
    }

    public function actionUnlockingVoucher() {
        if (!$this->request->post('voucherID') || !$this->request->post('salesNum')) {
            throw new HttpException(400);
        }

        $unlockingVoucher = Voucher::unlockingVoucher($this->request->post('voucherID'),
            $this->request->post('salesNum'));
        if (!$unlockingVoucher['status']) {
            throw new HttpException(404,
            Yii::t('app', $unlockingVoucher['message']));
        }
        return $unlockingVoucher['message'];
    }

    public function actionGetVoucherListMemberId() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }
        return ExternalVoucher::getVoucherListMemberID($this->request->post('salesNum'), $this->request->post('voucherType'));
    }

    public function actionGetVoucherListLoyalty() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }
        return ExternalVoucher::getVoucherListLoyalty($this->request->post('salesNum'), $this->request->post('voucherType'));
    }

    public function actionValidateVoucherLooplite(){
        if (!$this->request->post('memberID') || !$this->request->post('voucherID')) {
            throw new HttpException(400);
        }
        return ExternalVoucher::validateVoucherLoop($this->request->post('memberID'), $this->request->post('voucherID'));
    }

    public function actionValidateExternalVoucherStamps() {
        if (!$this->request->post('voucherCode')) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        $voucherValidation = ExternalVoucher::validateVoucherStamps(
            $this->request->post('voucherCode')
        );

        if (!$voucherValidation['voucher']) {
            if (in_array($voucherValidation['code'], [404, 400])) {
                throw new HttpException($voucherValidation['code'], Yii::t('app', $voucherValidation['status']));
            } else {
                throw new HttpException(500, Yii::t('app', $voucherValidation['status']));
            }
        }

        return $voucherValidation['voucher'];
    }

    public function actionRedeemOnlineVoucher() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $voucherModel = new Voucher([
            'attributes' => $this->request->post()
        ]);

        return $voucherModel->burnVouchers();
    }

    public function actionValidateVoucherPluxee() {
        if (!$this->request->post('terminalID') || !$this->request->post('voucherType') || !$this->request->post('salesNum') || !$this->request->post('voucherCode')) {
            throw new HttpException(400);
        }

        $result = ExternalVoucher::validateVoucherPluxee($this->request->post('terminalID'), $this->request->post('voucherType'), $this->request->post('salesNum'), $this->request->post('voucherCode'), $this->request->post('voucherAmount'));
        if ($result && $result['status'] == false) {
            throw new HttpException(400, $result['message']);
        }

        return $result;
    }

    public function actionValidateExternalUltraVoucher() {
        if (!$this->request->post('voucherCode') || !$this->request->post('voucherType') || !$this->request->post('salesNum') || !$this->request->post('terminalID')) {
            throw new HttpException(400, 'Invalid Parameter');
        }

        return ExternalVoucher::validateUltraVoucher(
            $this->request->post('voucherCode'), $this->request->post('voucherType'), $this->request->post('salesNum'), $this->request->post('terminalID')
        );
    }

}
