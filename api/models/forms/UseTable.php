<?php
namespace app\models\forms;

use app\models\TableUsage;
use DateTime;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;

/**
 * @property string $referenceID
 * @property string $username
 * @property TableUsage $tableUsageModel
 */
class UseTable extends Model {
    public $referenceID;
    private $username;
    private $tableUsageModel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['referenceID'], 'required'],
            [['referenceID'], 'string', 'max' => 20],
            [['referenceID'], 'validateTable']
        ];
    }

    public function validateTable($attribute) {
        $this->username = Yii::$app->user->identity->username;
        $this->tableUsageModel = TableUsage::find()
            ->andWhere(['referenceID' => $this->referenceID])
            ->one();
        if ($this->tableUsageModel) {
            $now = new DateTime();
            $timeDiff = strtotime($this->tableUsageModel->expiredTime) - strtotime($now->format('Y-m-d H:i:s'));

            if ($this->tableUsageModel->username != $this->username && $timeDiff > 0) {
                $this->addError($attribute, 'Reference ID is being used');
                $this->addError('username', $this->tableUsageModel->posUser->fullName);
            }
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->tableUsageModel) {
                $this->tableUsageModel->username = $this->username;
                $this->tableUsageModel->expiredTime = new Expression('DATE_ADD(NOW(), INTERVAl 4 SECOND)');
            } else {
                $this->tableUsageModel = new TableUsage();
                $this->tableUsageModel->referenceID = $this->referenceID;
                $this->tableUsageModel->username = $this->username;
                $this->tableUsageModel->expiredTime = new Expression('DATE_ADD(NOW(), INTERVAl 4 SECOND)');
            }
            if (!$this->tableUsageModel->save()) {
                Yii::error($this->tableUsageModel->errors);
                throw new Exception('Failed to save table usage');
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('referenceID', $ex->getMessage());
            return false;
        }
    }

}
