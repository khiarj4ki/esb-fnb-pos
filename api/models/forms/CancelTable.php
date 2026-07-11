<?php
namespace app\models\forms;

use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\Table;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\web\HttpException;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property string $cancelNotes
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 */
class CancelTable extends Model {
    public $tableID;
    public $salesNum;
    public $cancelNotes;
    public $salesModel;
    public $checkCurrentOrder;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'cancelNotes'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                    return $model->tableID == 0;
                }],
            [['tableID'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
            [['cancelNotes'], 'string', 'max' => 200],
            [['tableID'], 'validateTable'],
            [['checkCurrentOrder'], 'validateCurrentOrder']
        ];
    }

    public function validateCurrentOrder() {
        if ($this->checkCurrentOrder) {
            $countActiveSalesMenus = SalesMenu::findActive()
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->count();
            if ($countActiveSalesMenus > 0) {
                throw new HttpException(400, Yii::t('app', 'Cannot cancel table. Orders already submitted on this table'));
            }
        }
    }

    public function validateTable($attribute) {
        if ($this->tableID != 0) {
            $this->salesModel = SalesHead::findMainSales($this->tableID, $this->salesNum);
        } else {
            $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        }
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // @Notes: unlink table if any (delete & create log)
            $salesLink = SalesLink::findOne(['salesNum' => $this->salesModel->salesNum]);
            if ($salesLink) {
                SalesLink::deleteAll(['linkSalesNum' => $this->salesNum]);
                $dataSalesLink = (new Query())
                    ->select('sh.tableID, t.tableName, sh.salesNum')
                    ->from(SalesLink::tableName() . " sl")
                    ->innerJoin(['sh' => SalesHead::tableName()], ['AND', "sh.salesNum = sl.linkSalesNum", ['sl.salesNum' => $this->salesModel->salesNum]])
                    ->innerJoin(['t' => Table::tableName()], "t.tableID = sh.tableID")
                ->all();

                SalesLink::deleteAll(['salesNum' => $this->salesNum]);

                $linkTableLog = [
                    "tableID" => null,
                    "mainSalesModel" => $this->salesModel,
                    "salesLink" => $dataSalesLink
                ];
                Logging::save($this->salesNum, Logging::LINK_TABLE, $linkTableLog);
            }

            // @notes : stock menu RTS
            $salesHead = SalesHead::findOne(['salesNum' => $this->salesNum]);
            $modelSalesMenu = SalesMenu::find()
            ->where(['salesNum' => $this->salesNum])
            ->andWhere(['NOT IN','statusID', [12,19]])
            ->all();

            if ($salesHead) {
                foreach ($modelSalesMenu as $salesMenu) {
                    $validateStockModel = new ValidateStock();
                    $validateStockModel->salesNum = $this->salesNum;
                    $validateStockModel->menuID = $salesMenu->menuID;
                    $validateStockModel->qty = $salesMenu->qty;
                    $validateStockModel->transactionModeID = $salesHead->transactionModeID;
                    $validateStockModel->isCancelOrder = !in_array($salesMenu->statusID, [ 12, 19 ]);
        
                    $result = $validateStockModel->validateStock();
                    if($result){
                        throw new Exception(json_encode($result));
                    }
                }
            }

            // @Notes: 12 = Cancel
            SalesHead::updateAll([
                'salesDateOut' => new Expression('NOW()'),
                'additionalInfo' => $this->cancelNotes,
                'statusID' => 12,
                'editedBy' => Yii::$app->get('user')->id,
                'editedDate' => new Expression('NOW()'),
                'syncDate' => null
                ], ['salesNum' => $this->salesNum]);

            SalesMenu::updateAll([
                'statusID' => 19,
                'editedBy' => Yii::$app->get('user')->id,
                'editedDate' => new Expression('NOW()')
                ],
                ['AND', 
                    ['salesNum' => $this->salesNum],
                    ['<>', 'statusID', 19]]);

            SalesMenuExtra::updateAll(['statusID' => 19],
                ['salesNum' => $this->salesNum]);

            Logging::save($this->salesNum, Logging::CANCEL_TABLE, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('cancelNotes', $ex->getMessage());
            return false;
        }
    }

}
