<?php
namespace app\models\forms;

use app\models\SalesHead;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property string $salesNum
 * @property string $remarks
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 */
class UpdateRemarks extends Model {
    public $salesNum;
    public $remarks;
    public $salesModel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum'], 'required'],
            [['salesNum'], 'string', 'max' => 20],
            [['remarks'], 'string', 'max' => 200],
            [['salesNum'], 'validateSalesNum']
        ];
    }

    public function validateSalesNum($attribute) {
        $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        $error = false;
        if (!$this->salesModel) {
            $error = true;
        }

        if ($error) {
            $this->addError($attribute, 'Invalid sales number');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            SalesHead::updateAll([
                'remarks' => $this->remarks,
                'syncDate' => null
                ], ['=', 'salesNum', $this->salesModel->salesNum]);

            Logging::save($this->salesModel->salesNum, Logging::EDIT_REMARKS,
                $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('remarks', $ex->getMessage());
            return false;
        }
    }

}
