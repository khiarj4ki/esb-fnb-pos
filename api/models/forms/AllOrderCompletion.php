<?php

namespace app\models\forms;

use Yii;
use yii\base\Exception;
use yii\base\Model;

/**
 * @property string $salesNum
 * @property int $viewMode
 * @property string $errorMessage
 * 
 */
class AllOrderCompletion extends Model
{

    public $order;
    public $errorMessage;
    public $viewMode;
    public $completedDate;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order', 'viewMode', 'completedDate'], 'required'],
            [['viewMode'], 'integer'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->order as $data) {
                if (in_array($data['statusID'], [13, 14, 34])) {
                    if (count($data['packages']) > 0 && ($data['statusPackages'] == 0)) {
                        foreach ($data['packages'] as $package) {
                            $orderCompletionModel = new OrderCompletion();
                            $orderCompletionModel->salesNum = $package['salesNum'];
                            $orderCompletionModel->salesMenuID = $package['ID'];
                            $orderCompletionModel->qty = $package['qty'];
                            $orderCompletionModel->viewMode = $this->viewMode;
                            $orderCompletionModel->completedDate = $this->completedDate;
                            if (!$orderCompletionModel->save()) {
                                throw new Exception(json_encode($orderCompletionModel->getErrors()));
                            }
                        }
                    } else {
                        $orderCompletionModel = new OrderCompletion();
                        $orderCompletionModel->salesNum = $data['salesNum'];
                        $orderCompletionModel->salesMenuID = $data['ID'];
                        $orderCompletionModel->qty = $data['qty'];
                        $orderCompletionModel->viewMode = $this->viewMode;
                        $orderCompletionModel->completedDate = $this->completedDate;
                        if (!$orderCompletionModel->save()) {
                            throw new Exception(json_encode($orderCompletionModel->getErrors()));
                        }
                    }
                }
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }
}
