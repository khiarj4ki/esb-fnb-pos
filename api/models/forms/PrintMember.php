<?php
namespace app\models\forms;

use app\components\ExtPrinter;
use app\models\Branch;
use app\models\DepositWithdrawalHead;
use app\models\Enums\EnumInterface;
use app\models\Enums\PrinterTypeInterface;
use app\models\MemberDeposit;
use app\models\PosUser;
use app\models\Setting;
use app\models\Station;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * @property string $transNum
 * @property int $stationID
 * 
 * PRIVATE
 * @property Printer $printer
 * @property array $settings
 * @property Station $stationModel
 * @property MemberDeposit $deposit
 * @property DepositWithdrawalHead $withdrawal
 */
class PrintMember extends Model {
    const SCENARIO_DEPOSIT = 'deposit';
    const SCENARIO_WITHDRAWAL = 'withdrawal';

    public $transNum;
    public $stationID;
    public $printer;
    public $settings;
    public $stationModel;
    public $deposit;
    public $withdrawal;
    public $enabledImage;
    public $availableDeposit;
    public $rePrintMember = null;
    public $apiUrl;
    public $username;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['transNum', 'stationID'], 'required'],
            [['transNum'], 'string', 'max' => 20],
            [['stationID'], 'integer'],
            [['availableDeposit', 'rePrintMember', 'apiUrl', 'username'], 'safe'],
            [['transNum'], 'validateTransNum']
        ];
    }

    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->apiUrl = Setting::getApiUrl();
        $this->username = Yii::$app->user->identity->username;
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_DEPOSIT] = ['transNum', 'stationID'];
        $scenarios[self::SCENARIO_WITHDRAWAL] = ['transNum', 'stationID'];

        return $scenarios;
    }

    public function validateTransNum($attribute) {
        if ($this->scenario == self::SCENARIO_DEPOSIT) {
            $this->deposit = MemberDeposit::find()
                ->with('member')
                ->with('paymentMethod')
                ->andWhere(['memberDepositNum' => $this->transNum])
                ->andWhere(['statusID' => 3])
                ->one();
            if (!$this->deposit) {
                $this->addError($attribute, 'Invalid member deposit number');
            }
        } else if ($this->scenario == self::SCENARIO_WITHDRAWAL) {
            $this->withdrawal = DepositWithdrawalHead::find()
                ->with('member')
                ->with('paymentMethod')
                ->andWhere(['depositWithdrawalNum' => $this->transNum])
                ->andWhere(['statusID' => 3])
                ->one();
            if (!$this->withdrawal) {
                $this->addError($attribute, 'Invalid member deposit number');
            }
        } else {
            $this->addError($attribute, 'Scenario cannot be blank');
        }
    }

    public function doPrint() {
        if (!$this->validate()) {
            return false;
        }

        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();
        if (!$this->stationModel) {
            return false;
        }

        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();
        
        $memberMode = Setting::getSetting('POS', 'Member Mode');
        $memberMode = $memberMode ? $memberMode->value1 : 'offline';

        $this->settings = Setting::getPrintingSettings();
        $this->settings['Other Tax Text'] = $branchModel->additionalTaxName;

        try {
            $connector = Station::getConnectorByModel($this->stationModel,
                    $this->transNum);
            
            if($connector == null){
                throw new Exception("Failed to print. Connector not found", 400);
            }

            $this->printer = new ExtPrinter($connector);
            $printer = $this->printer;

            $printingCount = 3;
            if (isset($this->settings['Deposit Withdrawal Print Counter'])) {
                $printingCount = (int) $this->settings['Deposit Withdrawal Print Counter'];
            }

            for ($i = 0; $i < $printingCount; $i++) {
                $this->printHeader($branchModel);
                switch ($memberMode) {
                    case 'online':
                        $this->printDetailforOnline();
                        break;
                    default: //offline
                        $this->printDetail();
                        break;
                }
                $this->printFooter($branchModel);

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 13) {
                    $printer->feed(2);
                } else if ($this->stationModel->printerTypeID == '5') {
                    $printer->feed(2);
                } else if ($this->stationModel->printerTypeID == 15) {
                    if ($this->stationModel->flagAutocut == '1') {
                        $printer->feed(2);
                    }
                } else {
                    if ($this->stationModel->flagAutocut == '1') {
                        $printer->cut(Printer::CUT_PARTIAL);
                    }
                }
            }

            if($this->rePrintMember) {
                $memberDepositNum = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberDepositNum : $this->withdrawal->depositWithdrawalNum;
                if ($this->scenario == self::SCENARIO_DEPOSIT)
                    Logging::save($memberDepositNum, Logging::REPRINT_MEMBER_DEPOSIT, $this->deposit, $this->availableDeposit);

                if ($this->scenario == self::SCENARIO_WITHDRAWAL)
                    Logging::save($memberDepositNum, Logging::REPRINT_WITHDRAWAL_DEPOSIT, $this->withdrawal, $this->availableDeposit);
            }

            $printer->close();
        } catch (Exception $ex) {
            Yii::warning($ex);
        }
    }

    private function printHeader($branchModel) {
        $printer = $this->printer;
        $this->enabledImage = FALSE;
        // @Notes: Printer Type 1:Thermal, 2:Sticker, 3:Dot Matrix, 4:MPOP
        // @Notes: Printer Connection 1:Network, 2:Windows, 3:Android
        if ($this->stationModel->printerTypeID == '1' || $this->stationModel->printerTypeID == '3' || $this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15 ||
            $this->stationModel->printerConnectionID == '1' || $this->stationModel->printerConnectionID == '2') {
            $this->enabledImage = TRUE;
        }
        $charLength = $this->stationModel->characterPerLine;

        //@Notes: inserting image at header
        if ($this->enabledImage == TRUE) {
            $branchModel = Branch::find()
                    ->andWhere([
                        Branch::tableName() . '.flagActive' => 1,
                        Branch::tableName() . '.branchID' => Yii::$app->user->identity->branchID
                    ])->one();
            if ($branchModel) {
                $filename = 'pic-' . $branchModel->branchCode;
                $inputFileName = Yii::$app->basePath . '/web/images/' . $filename . '.png';
                if (file_exists($inputFileName)) {
                    $img = EscposImage::load($inputFileName);

                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                    } else {
                        $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                    }

                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->bitImageMpop($img);
                    } else {
                        $printer->bitImage($img);
                    }

                   
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {

            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        foreach (explode('>><<', $branchModel->printingHeader) as $lineHeader) {
            $printer->text($lineHeader);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if($this->rePrintMember){
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_WIDTH );
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT | ExtPrinter::MODE_DOUBLE_WIDTH );
            $printer->text(Yii::t('app', ucfirst('Reprint')));
            $printer->feed(2);
            $printer->setJustification(ExtPrinter::JUSTIFY_LEFT);

            if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            $printer->initialize();
        }
    }

    private function printDetail() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $transNum = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberDepositNum : $this->withdrawal->depositWithdrawalNum;
        $printer->text(str_pad(Yii::t('app',
                    ($this->scenario == self::SCENARIO_DEPOSIT ? 'Deposit' : 'Withdrawal') . ' Number'),
                18, ' '));
        $printer->text(' : ');
        $printer->text($transNum);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $transDate = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberDepositDate : $this->withdrawal->depositWithdrawalDate;
        $printer->text(str_pad(Yii::t('app',
                    ($this->scenario == self::SCENARIO_DEPOSIT ? 'Deposit' : 'Withdrawal') . ' Date'),
                18, ' '));
        $printer->text(' : ');
        $printer->text(date_format(date_create($transDate), 'd-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $memberCode = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->member->memberCode : $this->withdrawal->member->memberCode;
        $printer->text(str_pad(Yii::t('app', 'Member Code'), 18, ' '));
        $printer->text(' : ');
        $printer->text($memberCode);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $memberName = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->member->memberName : $this->withdrawal->member->memberName;
        $printer->text(str_pad(Yii::t('app', 'Member Name'), 18, ' '));
        $printer->text(' : ');
        $printer->text($memberName);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $paymentMethod = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->paymentMethod->paymentMethodName : $this->withdrawal->paymentMethod->paymentMethodName;
        $printer->text(str_pad(Yii::t('app', 'Payment Method'), 18, ' '));
        $printer->text(' : ');
        $printer->text($paymentMethod);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->depositTotal : $this->withdrawal->withdrawalTotal;
        $printer->text(str_pad(Yii::t('app',
                    ($this->scenario == self::SCENARIO_DEPOSIT ? 'Deposit' : 'Withdrawal') . ' Total'),
                18, ' '));
        $printer->text(' : ');
        $printer->text(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        // @reprint feature
        if ($this->rePrintMember) {
            $memberCode = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberCode : $this->withdrawal->memberCode;
            $this->availableDeposit = MemberDeposit::getOutstandingDeposit($memberCode);
            $printer->text(str_pad(Yii::t('app', 'Current Deposit'), 18,
                        ' '));
            $printer->text(' : ');
            $printer->text(number_format($this->availableDeposit, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            if ($this->stationModel->printerTypeID == '4') {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        } else {
            $memberCode = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberCode : $this->withdrawal->memberCode;
            $deposit = isset($this->availableDeposit) ? $this->availableDeposit : MemberDeposit::getOutstandingDeposit($memberCode);
            $printer->text(str_pad(Yii::t('app', 'Available Deposit'), 18, ' '));
            $printer->text(' : ');
            $printer->text(number_format($deposit, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $notes = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->additionalInfo : $this->withdrawal->additionalInfo;
        $printer->text(str_pad(Yii::t('app', 'Notes'), 18, ' '));
        $printer->text(' : ');
        $printer->text($notes);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $cashier = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->creator->fullName : $this->withdrawal->creator->fullName;
        $printer->text(str_pad(Yii::t('app', 'Cashier'), 18, ' '));
        $printer->text(' : ');
        $printer->text($cashier);

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }

    private function printDetailforOnline() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $memberMode = Setting::getSetting('POS', 'Member Mode');

        $transNum = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberDepositNum : $this->withdrawal->depositWithdrawalNum;
        $printer->text(str_pad(Yii::t('app', 'No'),
                18, ' '));
        $printer->text(' : ');
        $printer->text($transNum);
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $transDate = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberDepositDate : $this->withdrawal->depositWithdrawalDate;
        $printer->text(str_pad(Yii::t('app', 'Date'),
                18, ' '));
        $printer->text(' : ');
        $printer->text(date_format(date_create($transDate), 'd-m-Y'));
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $cashier = $this->scenario == self::SCENARIO_DEPOSIT ? ($this->deposit->creator ? $this->deposit->creator->fullName : '-') : ($this->withdrawal->creator ? $this->withdrawal->creator->fullName : '-');
        $printer->text(str_pad(Yii::t('app', 'Cashier'), 18, ' '));
        $printer->text(' : ');
        $printer->text($cashier);

        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $memberName = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->member->memberName : $this->withdrawal->member->memberName;
        $printer->text(str_pad(Yii::t('app', 'Member Name'), 18, ' '));
        $printer->text(' : ');
        $printer->text($memberName);
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($memberMode && $memberMode->value1 == "online") {
            $notes = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->additionalInfo : $this->withdrawal->additionalInfo;
            if($notes) {
                $printer->text(str_pad(Yii::t('app', 'Notes'), 18, ' '));
                $printer->text(' : ');
                $printer->text($notes);
                if ($this->stationModel->printerTypeID == '4') {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text($this->scenario == self::SCENARIO_DEPOSIT ? "Deposit" : "Withdrawal");
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }


        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->depositTotal : $this->withdrawal->withdrawalTotal;
        
        $printer->text(str_pad(Yii::t('app', ($this->scenario == self::SCENARIO_DEPOSIT ? 'Deposit' : 'Withdrawal') . ' Amount'), $charLength - 15,
                    ' ', STR_PAD_LEFT));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $paymentMethod = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->paymentMethod : $this->withdrawal->paymentMethod;
        $paymentMethodName = $paymentMethod->paymentMethodName;
        if (!empty($paymentMethod->accountName)) {
            $paymentMethodName .= " - " . $paymentMethod->accountName;
        }

        if($this->scenario == self::SCENARIO_DEPOSIT){
            $printer->text(str_pad('Payment', $charLength - 15,
                ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($total,
                    $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
        }
        
        $paymentMethodSubStrName = strlen($paymentMethodName) > $charLength - 13 ? substr($paymentMethodName,
        0, $charLength - 13) : $paymentMethodName;
        $printer->text(str_pad("($paymentMethodSubStrName)", $charLength - 15,
                ' ', STR_PAD_LEFT));
        
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
            $printer->feed(1);
        }

        // @reprint feature
        if ($this->rePrintMember) {
            $this->availableDeposit = $this->scenario == self::SCENARIO_DEPOSIT ? $this->getOutstandingMemberDepositOnline() : $this->getOutstandingMemberDepositOnlineWithdrawal();
            $printer->text(str_pad(Yii::t('app', 'Current Deposit'), $charLength - 15,
                        ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($this->availableDeposit, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4') {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        } else {
            $printer->text(str_pad(Yii::t('app', 'Available Deposit'), $charLength - 15,
            ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($this->availableDeposit, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4') {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function printFooter($branchModel) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        foreach (explode('>><<', $branchModel->printingFooter) as $lineFooter) {
            $printer->text($lineFooter);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    public function getOutstandingMemberDepositOnline() {

        $memberDepositNum = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberDepositNum : null;
        $memberCode = $this->scenario == self::SCENARIO_DEPOSIT ? $this->deposit->memberCode : null;

        $response = self::getHttpClient('/deposit')->addData([
            'memberDepositNum' => $memberDepositNum,
            'memberCode' => $memberCode
        ])->send();

        $responseData = $response->getData();
        if (isset($responseData['memberCode'])) {
            return $responseData['activeBalance'];
        } else {
            return 0;
        }
    }
    public function getOutstandingMemberDepositOnlineWithdrawal() {

        $depositWithdrawalNum = $this->scenario == self::SCENARIO_WITHDRAWAL ? $this->withdrawal->depositWithdrawalNum : null;
        $memberCode = $this->scenario == self::SCENARIO_WITHDRAWAL ? $this->withdrawal->memberCode : null;

        $response = self::getHttpClient('/withdrawal')->addData([
            'depositWithdrawalNum' => $depositWithdrawalNum,
            'memberCode' => $memberCode
        ])->send();

        $responseData = $response->getData();
        if (isset($responseData['memberCode'])) {
            return $responseData['activeBalance'];
        } else {
            return 0;
        }
    }

    public function getHttpClient($action)
    {
        $client = new Client();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];

        return $client->post($this->apiUrl . '/erp/member' . $action)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                'data-auth-username' =>  $this->getPasswordSalt()['username'],
                'data-auth-password' =>  $this->getPasswordSalt()['password'],
                'data-auth-salt' =>  $this->getPasswordSalt()['salt']
            ]);
    }

    private function getPasswordSalt()
    {
        $posUser = PosUser::find()->where(['username' => $this->username])->one();
        return [
            'username' => $this->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public static function apiError($errorCode = 500, $errorMsg = 'Internal Server Error', $errorData = null)
    {
        $errorCode = $errorCode <= 100 || $errorCode >= 600 ? 500 : $errorCode;
        $errorResponse = [
            'path' => Yii::$app->request->absoluteUrl,
            'code' => $errorCode,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $errorMsg
        ];
        if ($errorData != null) {
            $errorResponse['data'] = $errorData;
        }

        Yii::$app->response->statusCode = $errorCode;
        return $errorResponse;
    }

}
