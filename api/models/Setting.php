<?php

namespace app\models;

use app\models\forms\Logging;
use app\models\forms\UpdateKiosk;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_setting".
 *
 * @property string $key1
 * @property string $key2
 * @property string $value1
 * @property string $value2
 */
class Setting extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_setting';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['key1'], 'required'],
            [['key1', 'key2'], 'string', 'max' => 100],
            [['value1', 'value2'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'key1' => 'Key1',
            'key2' => 'Key2',
            'value1' => 'Value1',
            'value2' => 'Value2'
        ];
    }

    public static function getSetting($key1, $key2) {
        return Setting::findOne(['key1' => $key1, 'key2' => $key2]);
    }

    public static function getValue1($key1, $key2) {
        $model = Setting::findOne(['key1' => $key1, 'key2' => $key2]);
        if ($model) {
            return $model->value1;
        }

        return null;
    }

    public static function getValue2($key1, $key2) {
        $model = Setting::findOne(['key1' => $key1, 'key2' => $key2]);
        if ($model) {
            return $model->value2;
        }

        return null;
    }

    public static function getCurrentBranch() {
        $model = Setting::findOne(['key1' => 'Local Setting', 'key2' => 'Branch ID']);
        return $model ? $model->value1 : '';
    }

    public static function getMemberIdBranchCode() {
        $model = Setting::findOne(['key1' => 'External', 'key2' => 'Member ID Branch Code']);
        return $model ? $model->value1 : '';
    }

    public static function getPrintingSettings() {
        return Setting::find()
                ->select('value1')
                ->andWhere(['key1' => 'POS'])
                ->indexBy('key2')
                ->column();
    }

    public static function getLocalSettings() {
        return Setting::find()
                        ->select('value1')
                        ->andWhere(['key1' => 'Local Setting'])
                        ->indexBy('key2')
                        ->column();
    }

    public static function getOtherVat() {
        return Setting::find()
                        ->select('value1')
                        ->where(['key1' => 'VAT'])
                        ->andWhere(['key2' => 'Value'])
                        ->scalar();
    }
    
    public static function getPrintShiftSetting() {
        return Setting::find()
            ->select('value1')
            ->andWhere(['key1' => 'POS'])
            ->andWhere(['IN', 'key2', [
                'Print Stock Branch Menu',
                'Print Cancelled Menu',
                'Print Cancelled Menu Summary',
                'Print Closing Notes',
                'Print Custom Menu Sales',
                'Print Deposit Detail',
                'Print Deposit Summary',
                'Print Withdrawal Detail',
                'Print Withdrawal Summary',
                'Print Daily Member Summary',
                'Print Non Sales Bill Summary',
                'Print Non Sales By Menu',
                'Print Non Sales Menu Summary',
                'Print Non Sales Payment by Cashier',
                'Print Non Sales Payment Method Detail',
                'Print Non Sales Payment Method Summary',
                'Print Payment by Cashier',
                'Print Payment Method Detail',
                'Print Payment Method Summary',
                'Print Pending Sales',
                'Print Promotion Summary',
                'Print Quick Service Table Text',
                'Print Sales by Menu Category',
                'Print Sales by Menu Category Detail',
                'Print Sales By Menu Group',
                'Print Sales by Menu Qty',
                'Print Sales by Menu Qty Value',
                'Print Sales by Menu Value',
                'Print Sales by Mode',
                'Print Sales by Type',
                'Print Sales by Table Section',
                'Print Sales By Visit Purpose',
                'Print Sales Menu by Mode',
                'Print Sales Menu Package',
                'Print Sales Per Date',
                'Print Sales per Menu Category',
                'Print Sales Voucher Usage',
                'Print Shift Sales by Menu Value',
                'Print Shift Summary',
                'Print Special Price Summary',
                'Print Void Payment Detail',
                'Print Void Payment Summary',
                'Queue Number'
                ]
            ])
            ->indexBy('key2')
            ->column();
    }

    public static function getApiKey() {
        $model = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['key2' => 'Api Key'])
            ->one();

        if (!$model) {
            return null;
        }

        return Yii::$app->security->decryptByKey(base64_decode($model->value1),
                Yii::$app->params['key']);
    }

    public static function getApiUrl() {
        $model = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['key2' => 'Api Url'])
            ->one();

        if (!$model) {
            return null;
        }

        return Yii::$app->security->decryptByKey(base64_decode($model->value1),
                Yii::$app->params['key']);
    }
    
    public static function getSelfOrderSetting($key2) {
        $model = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['key2' => $key2])
            ->one();

        if (!$model) {
            return null;
        }

        return Yii::$app->security->decryptByKey(base64_decode($model->value1),
                Yii::$app->params['key']);
    }
    
    public static function getEZOSetting() {
        return Setting::find()
                        ->select('value1')
                        ->andWhere(['key1' => 'EZO'])
                        ->indexBy('key2')
                        ->column();
    }

    public static function getExternalSettings() {
        return Setting::find()
                        ->select('value1')
                        ->andWhere(['key1' => 'External'])
                        ->indexBy('key2')
                        ->column();
    }

    public static function getMemberMode(){
        return Setting::find()
                        ->select('value1')
                        ->andWhere(['key1' => 'POS'])
                        ->andWhere(['key2' => 'Member Mode'])
                        ->scalar();
    }

    public static function saveLocalSetting($key2, $value1, $enc) {
        if ($enc) {
            $value1 = base64_encode(Yii::$app->security->encryptByKey($value1,
                    Yii::$app->params['key']));
        }

        $model = new Setting();
        $model->key1 = 'Local Setting';
        $model->key2 = $key2;
        $model->value1 = strval($value1);
        $model->value2 = $enc ? 'Enc' : null;

        return $model->save();
    }

    public static function getQueueDisplayColorSetting() {
        $modelColorSetting = Setting::find()
                        ->select(['key2', 'value1'])
                        ->andWhere(['key1' => 'POS'])
                        ->andWhere(['IN', 'key2', [
                            'Color Code Display On Progress',
                            'Color Code Display Done',
                            'Color Code Display Queue'
                        ]])
                        ->all();

        $result = [];
        if ($modelColorSetting) {
            foreach ($modelColorSetting as $queueColorSetting) {
                $result[] = [
                    $queueColorSetting['key2'],
                    $queueColorSetting['value1'],
                    Menu::defineMenuTextColor($queueColorSetting->value1)
                ] ;
            }
        }
        return $result;
    }

    public static function getExternalPaymentSetting($posExternalPaymentID) {
        $status = true;
        if ($posExternalPaymentID == 'qrisnobu') {
            $storeID = Setting::getValue1('POS', 'StoreID QRIS Nobu Bank');
            $terminalID = Setting::getValue1('POS', 'Terminal ID QRIS Nobu Bank');
            if ($storeID == '' || $terminalID == '') $status = false; 
        } else if ($posExternalPaymentID == 'qrisotopay') {
            $merchantID = Setting::getValue1('POS', 'Merchant ID Qris OttoPay');
            if ($merchantID == '') $status = false;
        } else if ($posExternalPaymentID == 'qrisgpay') {
            $getMerchantID = BrandSetting::getBrandPosSetting('Merchant ID GPay');
            $merchantID = array_key_exists('Merchant ID GPay', $getMerchantID) ? $getMerchantID['Merchant ID GPay'] : '';
            if ($merchantID == '') $status = false;
        } else if ($posExternalPaymentID == 'qrisbri') {
            $merchantID = Setting::getValue1('POS', 'Merchant ID QRIS BRI');
            $terminalID = Setting::getValue1('POS', 'Terminal ID QRIS BRI');
            if ($merchantID != '' && $terminalID == '') {
                $status = false;
            }else if ($merchantID == '' && $terminalID != '') {
                $status = false;
            }
        }
        return [
            'status' => $status
        ];
    }

    public static function getEsoQsApiUrl() {
        return rtrim(Setting::getApiUrl(), "/") . "/esb-order/eso-qs/";
    }

    public static function getQoQiApiUrl() {
        return rtrim(Setting::getApiUrl(), "/") . "/esb-order/qoqi/";
    }

    public static function getEsoFsApiUrl() {
        return rtrim(Setting::getApiUrl(), "/") . "/esb-order/eso-fs/";
    }

    public static function getExternalVoucherToken() {
        $model = Setting::findOne(['key1' => 'Local Setting', 'key2' => 'External Voucher Token']);
        return $model ? $model->value1 : '';
    }

    public static function getTokenGlobalTix() {
        $model = Setting::findOne(['key1' => 'Local Setting', 'key2' => 'GlobalTix Token']);
        return $model ? $model->value1 : '';
    }

    public static function getTokenQrLippoParking() {
        $model = Setting::findOne(['key1' => 'Local Setting', 'key2' => 'Lippo Parking Voucher Token']);
        return $model ? $model->value1 : '';
    }

    public static function setNewVersion($key1, $key2, $latestVersion, $flagManualUpdate = false, $productType) {
        $loggingEvent = null;
        if ($productType != null) {
            if ($productType == 'tableSide') {
                $loggingEvent = Logging::UPDATE_TABLESIDE_VERSION;
            } else if ($productType == 'kiosk') {
                $loggingEvent = Logging::UPDATE_KIOSK_VERSION;
            } else if ($productType == 'ods') {
                $loggingEvent = Logging::UPDATE_ODS_VERSION;
            }
        }

        $newVersion = UpdateKiosk::getCurrentVersion($latestVersion);
        $checkVersion = self::getSetting($key1, $key2);
        if (!$checkVersion) {
            Yii::$app->db->createCommand()->insert(
                Setting::tableName(), [
                    'key1' => $key1,
                    'key2' => $key2,
                    'value1' => $latestVersion
                ]
            )->execute();

            if ($flagManualUpdate) UpdateKiosk::setLoggingSuccess(null, $newVersion, $loggingEvent);
        } else {
            $currentVersion = UpdateKiosk::getCurrentVersion($checkVersion->value1);
            $currentVersionID = (int) $currentVersion[0];
            $newVersionID = (int) $newVersion[0];
            if ($currentVersionID != $newVersionID) {
                Yii::$app->db->createCommand()->update(
                    Setting::tableName(), 
                    ['value1' => $latestVersion], "key2 = :key2", 
                    [':key2' => $key2]
                )->execute();

                if ($flagManualUpdate) UpdateKiosk::setLoggingSuccess($currentVersion, $newVersion, $loggingEvent);
            }
        }
    }
}
