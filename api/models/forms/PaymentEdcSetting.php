<?php
namespace app\models\forms;

use app\models\PaymentMethod;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property array $station
 */
class PaymentEdcSetting extends Model {
    public $paymentEdc;
    public $edcActive;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['edcActive', 'paymentEdc'], 'safe'],
        ];
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->paymentEdc as $paymentEdc) {
                if (isset($paymentEdc['updated'])) {
                    $paymentMethodModel = PaymentMethod::find()
                        ->andWhere(['paymentMethodID' => $paymentEdc['paymentMethodID']])
                        ->one();
                    if ($paymentMethodModel) {
                        $paymentMethodModel->paymentMethodID = $paymentEdc['paymentMethodID'];
                        $paymentMethodModel->paymentMethodTypeID = $paymentEdc['paymentMethodTypeID'];
                        $paymentMethodModel->posExternalPaymentID = $paymentEdc['posExternalPaymentID'];
                        $paymentMethodModel->edcWssUrl = $paymentEdc['edcWssUrl'];
                        $paymentMethodModel->edcPort = $paymentEdc['edcPort'];
                        $paymentMethodModel->flagEdcActive = $paymentEdc['flagEdcActive'];
                        if (!$paymentMethodModel->save()) {
                            
                            throw new Exception('Failed to update payment edc');
                        }
                    }
                }
            }
            Logging::save('-', Logging::EDIT_PAYMENT_EDC, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('paymentEdc', $ex->getMessage());
            return false;
        }
    }

    public function saveEdc() 
    {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->paymentEdc) {
                if (isset($this->paymentEdc['updated'])) {
                    $paymentMethodModel = PaymentMethod::find()
                        ->andWhere(['paymentMethodID' => $this->paymentEdc['paymentMethodID']])
                        ->one();
                    if ($paymentMethodModel) {
                        $paymentMethodModel->paymentMethodID = $this->paymentEdc['paymentMethodID'];
                        $paymentMethodModel->paymentMethodTypeID = $this->paymentEdc['paymentMethodTypeID'];
                        $paymentMethodModel->posExternalPaymentID = $this->paymentEdc['posExternalPaymentID'];
                        $paymentMethodModel->edcWssUrl = $this->paymentEdc['edcWssUrl'];
                        $paymentMethodModel->edcPort = $this->paymentEdc['edcPort'];
                        if (!$paymentMethodModel->save()) {
                            
                            throw new Exception('Failed to update payment edc');
                        }
                    }
                    
                }
            }
            
            Logging::save('-', Logging::EDIT_EDC_SETTING_KIOSK, $this->getAttributes());
            
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('paymentEdc', $ex->getMessage());
            return false;
        }
    }

}
