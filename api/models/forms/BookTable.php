<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\MapBranchVisitPurpose;
use app\models\Member;
use app\models\MenuTemplateHead;
use app\models\QuestionAnswer;
use app\models\SalesHead;
use app\models\SalesMergeTable;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\VisitPurpose;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $memberID
 * @property int $memberCode
 * @property int $visitPurposeID
 * @property int $paxTotal
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 * @property VisitPurpose $visitModel
 */
class BookTable extends Model {
    public $tableID;
    public $salesNum;
    public $bookNum;
    public $memberID;
    public $memberCode;
    public $employeeCode;
    public $employeeType;
    public $employeeName;
    public $visitPurposeID;
    public $visitorTypeID;
    public $paxTotal;
    public $salesModel;
    public $visitModel;
    public $updateOrderModel;
    public $flagInclusive;
    public $flagExternalAPI;
    public $flagExternalMemberID;
    public $flagExternalMemberPhone;
    public $flagExternalCardID;
    public $externalMemberName;
    public $externalMembershipTypeID;
    public $externalTransID;
    public $orderTimeOut;
    public $questionAnswers;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'visitPurposeID', 'paxTotal'], 'required'],
            [['tableID', 'memberID', 'visitPurposeID', 'paxTotal', 'flagInclusive', 'visitorTypeID'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
            [['memberID'], 'validateMember'],
            [['tableID'], 'validateTable'],
            [['visitPurposeID'], 'validateVisitPurpose'],
            [['externalMemberName'], 'validateExternalMemberName'],
            [['externalMembershipTypeID', 'flagExternalAPI', 'flagExternalMemberID', 'flagExternalMemberPhone', 'flagExternalCardID', 'employeeCode',
                'employeeType', 'employeeName', 'orderTimeOut', 'memberCode', 'bookNum', 'externalMemberName', 'externalTransID', 'questionAnswers'
            ], 'safe']
        ];
    }

    public function validateMember($attribute) {
        if ($this->memberID > 0 && !$this->memberCode) {
            $this->memberID = 0;
        }
        
        if ($this->memberCode) {
            $memberModel = Member::findActive()
                ->andWhere(['memberCode' => $this->memberCode])
                ->one();
            if (!$memberModel) {
                $this->addError($attribute, 'Invalid member ID');
            }
        }
    }

    public function validateTable($attribute) {

        $branchID = Setting::getCurrentBranch();
        $this->salesModel = SalesHead::find()
            ->where([salesHead::tableName() . '.tableID' => $this->tableID])
            ->andWhere([salesHead::tableName() . '.statusID' => 1])
            ->andWhere(['>', salesHead::tableName() . '.tableID', 0]) 
            ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
            ->andWhere(['IS', SalesHead::tableName() . '.salesDateOut', null])
            ->one();

        if ($this->salesModel) {
            $this->addError($attribute, 'Table ID Already Booked');
        }
    }

    public function validateExternalMemberName($attribute) {
        if ($this->externalMemberName && strlen($this->externalMemberName) > 100) {
            $this->externalMemberName = substr($this->externalMemberName, 0, 100);
        }
    }

    public function validateVisitPurpose($attribute) {
        $this->visitModel = VisitPurpose::findActive()
            ->andWhere(['visitPurposeID' => $this->visitPurposeID])
            ->one();
        if (!$this->visitModel) {
            $this->addError($attribute, 'Invalid visit purpose ID');
        }
        
        $queryInclusive = (new Query())
                ->select([
                    'b.flagInclusive'
                ])
                ->from(MapBranchVisitPurpose::tableName() . ' a')
                ->innerJoin(MenuTemplateHead::tableName() . ' b',
                    'b.menuTemplateID = a.menuTemplateID')
                ->andWhere(['a.visitPurposeID' => $this->visitPurposeID])
                ->one();
        
        if ($queryInclusive) {
            $this->flagInclusive = $queryInclusive['flagInclusive'];
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction('Serializable');
        try {
            if ($this->tableID == 0) {
                if ($this->salesNum == '') {
                    $this->insert();
                } else {
                    $this->salesModel = SalesHead::findOutstanding()
                        ->andWhere(['salesNum' => $this->salesNum])
                        ->one();
                    if ($this->salesModel) {
                        $this->update();
                    }
                }
            } else {
                if ($this->salesNum == '') {
                    $this->insert();
                } else {
                    $this->salesModel = SalesHead::findOutstanding()
                        ->joinWith('salesMergeTables')
                        ->andWhere(['OR',
                            [SalesHead::tableName() . '.tableID' => $this->tableID],
                            [SalesMergeTable::tableName() . '.tableID' => $this->tableID],
                        ])
                        ->andFilterWhere([SalesHead::tableName() . '.salesNum' => $this->salesNum])
                        ->one();
                    if (!$this->salesModel) {
                        $this->insert();
                    } else {
                        $this->update();
                    }
                }
            }

            if (isset($this->updateOrderModel)) {
                $updateModel = $this->updateOrderModel;
                $updateModel->salesNum = $this->salesNum;
                if (!$updateModel->save()) {
                    $orderError = $updateModel->errors;
                    $orderErrorMsg = $orderError['rejectedOrder'][0];
                    $this->addError("rejectedOrder", $orderErrorMsg);
                    $transaction->rollBack();
                    return false;
                }
            }

            $transaction->commit();

            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('tableID', $ex->getMessage());
            return false;
        }
    }

    protected function insert() {
        $branchID = Setting::getCurrentBranch();
        $printingSettings = Setting::getPrintingSettings();
        $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;

        $salesModel = new SalesHead([
            'attributes' => $this->getAttributes()
        ]);
        $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
        $salesModel->salesDate = ShiftLog::getShiftInDate();
        $salesModel->salesNum = AppHelper::createNewTransactionNumber('Sales',
                $salesModel->salesDate, $branchID);
        $salesModel->bookNum = $this->bookNum;
        $salesModel->salesDateIn = new Expression('NOW()');
        $salesModel->salesDateOut = null;
        $salesModel->branchID = $branchID;
        $salesModel->memberCode = $this->memberCode;
        $salesModel->orderFee = $this->visitPurposeID ? $this->visitModel->mapBranchVisitPurpose->orderFee : 0;
        $salesModel->flagInclusive = $this->flagInclusive;
        $salesModel->orderTimeOut = $this->orderTimeOut ? date('Y-m-d H:i:s', strtotime(' + ' . $this->orderTimeOut . ' minutes')) : null;
        $salesModel->queueNum = $printingAfterPayment == 1 && $this->tableID == 0 ? null : SalesHead::getQueueNumber($salesModel->salesNum, $salesModel->salesDate, $salesModel->branchID);

        if (!$salesModel->save()) {
            Yii::error($salesModel->errors);
            throw new Exception('Failed to save sales head');
        }
        $this->salesNum = $salesModel->salesNum;

        Logging::save($salesModel->salesNum,
            $salesModel->tableID != 0 ? Logging::BOOK_TABLE : Logging::CREATE_TAKE_AWAY,
            $this->getAttributes());
    }

    protected function update() {
        $this->salesModel->load(['SalesHead' => $this->getAttributes()]);
        $this->salesModel->orderTimeOut = $this->orderTimeOut ? date('Y-m-d H:i:s', strtotime(' + ' . $this->orderTimeOut . ' minutes')) : null;
        $this->salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
        if (!$this->salesModel->save()) {
            Yii::error($this->salesModel->errors);
            throw new Exception('Failed to update sales head');
        }

        Logging::save($this->salesModel->salesNum,
            $this->salesModel->tableID != 0 ? Logging::EDIT_TABLE : Logging::EDIT_TAKE_AWAY,
            $this->getAttributes());
    }

}
