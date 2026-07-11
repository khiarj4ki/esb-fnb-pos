<?php

namespace app\modules\v1\Member\Service\Action;

use app\modules\v1\Member\Dto\UpdateMemberRequest;
use app\modules\v1\Member\Entity\Model\UpdateMember;
use app\modules\v1\Member\Entity\Repository\MemberSalesRepository;
use Yii;
use yii\db\Exception;

class MemberUpdateAction extends UpdateMember implements MemberUpdateActionInterface
{
    /**
     * @param UpdateMemberRequest $request
     * @return UpdateMemberRequest
     */
    public function handle(UpdateMemberRequest $request): UpdateMemberRequest
    {
        $this->setAttributes($request->attributes, false);
        $transaction = Yii::$app->db->beginTransaction('Serializable');
        try {
            $this->processSave();
            
            $request->setDataResponse(true);
            $transaction->commit();
            return $request;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('tableID', $ex->getMessage());
            $request->setDataResponse(false);

            return $request;
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function processSaveQuickService(): void
    {
        if ($this->salesNum == '') {
            $this->insert();
            return;
        }

        $this->salesModel = MemberSalesRepository::findOutStandingQuickService($this->salesNum);
        if ($this->salesModel) {
            $this->update();
            return;
        }
        $this->insert();
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function processSaveFullService(): void
    {
        if ($this->salesNum == '') {
            $this->insert();
            return;
        }

        $this->salesModel = MemberSalesRepository::findOutStandingFullService($this->tableID, $this->salesNum);
        if (!$this->salesModel) {
            $this->insert();
            return;
        }
        $this->update();
    }

    /**
     * @throws Exception
     */
    protected function updateOrderModelIfSet()
    {
        if (!isset($this->updateOrderModel)) {
            return;
        }

        $updateModel = $this->updateOrderModel;
        $updateModel->salesNum = $this->salesNum;
        if (!$updateModel->save()) {
            $orderError = $updateModel->errors;
            $orderErrorMsg = $orderError['rejectedOrder'][0];
            $this->addError("rejectedOrder", $orderErrorMsg);

            throw new Exception(json_encode($orderErrorMsg));
        }

    }

    /**
     * @throws Exception
     */
    protected function processSave()
    {
        $this->processSaveMode($this->tableID);
        $this->updateOrderModelIfSet();
    }

    /**
     * @throws Exception
     */
    protected function processSaveMode(int $tableId)
    {
        if ($tableId == 0 ) {
             $this->processSaveQuickService();
            return;
        }

        $this->processSaveFullService();
    }
}