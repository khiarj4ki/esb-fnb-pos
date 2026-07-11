<?php

namespace app\models\forms;

use app\models\SalesHead;
use app\models\SalesMergeTable;
use app\models\Table;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property int $tableID
 * @property int $sourceTableID
 * @property string $
 * @property int $batchIDsourceSalesNum
 * 
 * PRIVATE
 * @property Table $tableModel
 * @property SalesHead $sourceSalesModel
 */
class MoveTable extends Model {
    public $tableID;
    public $sourceTableID;
    public $sourceSalesNum;
    public $tableModel;
    public $batchID;
    public $sourceSalesModel;
    public $salesNum;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'sourceTableID'], 'required'],
            [['sourceSalesNum'], 'required', 'when' => function ($model) {
                    return $model->sourceTableID == 0;
                }],
            [['tableID', 'sourceTableID'], 'integer'],
            [['sourceSalesNum'], 'string', 'max' => 20],
            [['tableID'], 'validateTable'],
            [['sourceTableID'], 'validateSourceTable']
        ];
    }

    public function validateTable($attribute) {
        $salesModel = SalesHead::findOutstanding()
            ->joinWith('salesMergeTables')
            ->andWhere(['OR',
                [SalesHead::tableName() . '.tableID' => $this->tableID],
                [SalesMergeTable::tableName() . '.tableID' => $this->tableID]
            ])
            ->one();
        if ($salesModel) {
            $this->addError($attribute, 'Invalid table ID');
        } else {
            $this->tableModel = Table::find()
                ->andWhere(['tableID' => $this->tableID])
                ->one();
        }
    }

    public function validateSourceTable($attribute) {
        if ($this->sourceTableID != 0) {
            $this->sourceSalesModel = SalesHead::findOutstanding()
                ->joinWith('salesMergeTables')
                ->andWhere(['OR',
                    [SalesHead::tableName() . '.tableID' => $this->sourceTableID],
                    [SalesMergeTable::tableName() . '.tableID' => $this->sourceTableID]
                ])
                ->one();
        } else {
            $this->sourceSalesModel = SalesHead::findOutstandingOrder()
                ->andWhere([salesHead::tableName() . '.salesNum' => $this->sourceSalesNum])
                ->one();
        }
        if (!$this->sourceSalesModel) {
            $this->addError($attribute,
                'Invalid source table ID or sales number');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $salesModel = SalesHead::findOutstanding()
                ->andWhere(['OR',
                    ['tableID' => $this->sourceTableID],
                    ['salesNum' => $this->sourceSalesNum],
                ])
                ->one();

            if ($this->sourceTableID == 0) {
                $salesModel = SalesHead::findOutstanding()
                    ->andWhere(['salesNum' => $this->sourceSalesNum])
                    ->one();
            }

            if ($salesModel) {
                $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                $salesModel->tableID = $this->tableID;
                $salesModel->billingPrintCount = 0;
                
                Yii::$app->db->createCommand()
                    ->update(
                        SalesHead::tableName(),
                        ['tableID' => $this->tableID, 'billingPrintCount' => 0],
                        ['salesNum' => $this->sourceSalesNum]
                    )
                    ->execute();
            } else {
                $salesMergeModel = SalesMergeTable::find()
                    ->joinWith('salesHead')
                    ->andWhere([SalesMergeTable::tableName() . '.tableID' => $this->sourceTableID])
                    ->andWhere(['IS', 'salesDateOut', null])
                    ->one();
                if ($salesMergeModel) {
                    $salesMergeModel->tableID = $this->tableID;
                    if (!$salesMergeModel->save()) {
                        
                        throw new Exception('Failed to move table');
                    }

                    $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                    $salesModel->billingPrintCount = 0;
                    if (!$salesModel->save()) {
                       
                        throw new Exception('Failed to update main table');
                    }
                }
            }

            $this->salesNum = $this->sourceSalesNum;

            Logging::save($this->sourceSalesModel->salesNum,
                Logging::MOVE_TABLE, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('destinationTableID', $ex->getMessage());
            return false;
        }
    }

}
