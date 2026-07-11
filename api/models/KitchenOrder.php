<?php

namespace app\models;

use app\models\SalesMenu;
use app\models\Setting;
use app\models\forms\OrderCompletion;
use yii\db\ActiveRecord;
use yii\db\Expression;
use Yii;
use yii\base\Exception;


class KitchenOrder extends ActiveRecord {
    const STATUS_PENDING = 'PENDING';
    const STATUS_SUCCESS_KITCHEN = 'SUCCESS KITCHEN';
    const STATUS_SUCCESS_CHECKER = 'SUCCESS CHECKER';
    public $items;
    public $viewMode;

    public static function tableName() {
        return 'tr_kitchen_order';
    }

    public function rules() {
        return [
            [['code', 'salesNum', 'data', 'status', 'createdDate', 'editedDate', 'items', 'viewMode'], 'safe']
        ];
    }

    public function __destruct()
    {
        $this->items = [];
        $this->code = null;
    }

    public function setData($printingModeID, $salesNum, $printOrder)
    {
        if (!$this->items) {
            $this->items = [];
        }

        if ($printingModeID > 1) {
            $salesMenuID = 0;
            $qty = 0;
            if ($printingModeID == 2) {
                $salesMenuID = isset($printOrder['ID']) ? $printOrder['ID'] : substr(microtime(true) * 10000, -10);
                $qty = isset($printOrder['qty']) ? $printOrder['qty'] : 0;
            } else if ($printingModeID == 3) {
                $salesMenuID = isset($printOrder['ID']) ? $printOrder['ID'] : substr(microtime(true) * 10000, -10);
                $qty = 1;
            }

            if (array_search($salesMenuID, array_column($this->items, 'salesMenuID')) !== FALSE) {
                return;
            } else {
                $this->items[] = [
                    'salesNum' => $salesNum,
                    'salesMenuID' => $salesMenuID,
                    'qty' => $qty
                ];
            }
        } else {
            $salesMenuID = isset($printOrder['ID']) ? $printOrder['ID'] : substr(microtime(true) * 10000, -10);
            $qty = isset($printOrder['qty']) ? $printOrder['qty'] : 0;
            if (array_search($salesMenuID, array_column($this->items, 'salesMenuID')) !== FALSE) {
                return;
            } else {
                $this->items[] = [
                    'salesNum' => $salesNum,
                    'salesMenuID' => $salesMenuID,
                    'qty' => $qty
                ];
            }
        }
        
        $this->salesNum = $salesNum;
        $this->status = self::STATUS_PENDING;
        $this->createdDate = new Expression('NOW()');
    }

    public function getCode()
    {
        $this->code = (int) ceil((microtime(true) * 100));
    }

    public function generateQR($printer, $stationModel)
    {
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->barcode($this->code, \Mike42\Escpos\Printer::BARCODE_ITF);

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        $printer->text($this->code);

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_LEFT);
        }
    }

    public function saveModel()
    {
        $this->data = json_encode($this->items);
        $this->save();
    }

    private function checkCancelQty($salesNum, $salesMenuID)
    {
        $model = SalesMenu::find()->where(['ID' => $salesMenuID])->one();
        if ($model) {
            $menuID = $model->menuID;

            $statusFilter = [
                'OR',
                'tr_salesmenu.statusID = 19',
                'tr_salesmenu.statusID = 24'
            ];
    
            $cancelQty = SalesMenu::find()
                        ->where(['salesNum' => $salesNum, 'menuID' => $menuID])
                        ->andWhere($statusFilter)
                        ->sum("qty");
    
            if ($cancelQty > 0) {
                return $cancelQty;
            }
        }
        return 0;
    }

    public function completeAllOrder()
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $dataKitchen = $this->getDataByStatus($this->code);
            if (!$dataKitchen) {
                throw new Exception('Kitchen order not found');
            }

            $item = json_decode($dataKitchen->data);
            if (is_array($item) && count($item) > 0) {
                foreach ($item as $row) {
                    $finishQty = $row->qty;
                    if ($finishQty > 1 && $finishQty > $this->checkCancelQty($row->salesNum, $row->salesMenuID)) {
                        $finishQty = $row->qty - $this->checkCancelQty($row->salesNum, $row->salesMenuID);
                    }
                    $orderCompletionModel = new OrderCompletion();
                    $orderCompletionModel->viewMode = $this->viewMode;
                    $orderCompletionModel->salesMenuID = $row->salesMenuID;
                    $orderCompletionModel->salesNum = $row->salesNum;
                    $orderCompletionModel->qty = $finishQty;

                    if (!$orderCompletionModel->save()) {
                        throw new Exception(current($orderCompletionModel->errors)[0]);
                    }

                }
            }
            $this->updateStatus($this->code);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('code', $ex->getMessage());
            return false;
        }
    }

    public function getDataByStatus($code)
    {
        $mode = Setting::find()
                ->where(['key1' => 'POS'])
                ->andWhere(['key2' => 'ODS Mode'])
                ->one()
                ->value1;
    
        $status = self::STATUS_PENDING;
        if ($mode) {
            if ($mode == 2) {
                $status = self::STATUS_PENDING;
            } else if ($mode == 1) {
                if ($this->viewMode == 1) {
                    $status = self::STATUS_PENDING;
                } else if ($this->viewMode == 2) {
                    $status = self::STATUS_SUCCESS_KITCHEN;
                }
            }
        }

        $result = self::find()->where(['code' => $code, 'status' => $status])->one();
        return $result;
    }

    public function updateStatus($code)
    {
        $mode = Setting::find()
                ->where(['key1' => 'POS'])
                ->andWhere(['key2' => 'ODS Mode'])
                ->one()
                ->value1;
    
        $status = self::STATUS_PENDING;
        if ($mode) {
            if ($mode == 2) {
                $status = self::STATUS_SUCCESS_CHECKER;
            } else if ($mode == 1) {
                if ($this->viewMode == 1) {
                    $status = self::STATUS_SUCCESS_KITCHEN;
                } else if ($this->viewMode == 2) {
                    $status = self::STATUS_SUCCESS_CHECKER;
                }
            }
        }

        $model = self::find()->where(['code' => $code])->one();
        if ($model) {
            $model->status = $status;
            $model->editedDate = new Expression('NOW()');
            $model->save();
        }
    }

}
