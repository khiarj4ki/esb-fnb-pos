<?php

namespace app\modules\V1\Tables\CancelTable\Service\Action;

use app\models\forms\CancelTable;
use app\models\forms\Logging;
use app\models\forms\ValidateStock;
use app\models\SalesLink;
use app\modules\V1\Tables\CancelTable\Entity\Repository\CancelTableRepository;
use Exception;
use Yii;

class CancelTableServiceAction extends CancelTable
{

    protected $repository;

    public function __construct(
        CancelTableRepository $cancelTableRepository
    ) {
        $this->repository = $cancelTableRepository;
    }
    /**
     * @param $dto
     * @return mixed
     */
    public function handle($dto)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$dto->validate()) {
                return false;
            }

            $this->deleteSalesLink($dto);
            $this->validatestockMenuRTS($dto);
            $this->deleteSalesData($dto);
            $this->loggingRecord($dto, Logging::CANCEL_TABLE, $dto->attributes);

            $transaction->commit();
            return $dto->salesNum;
        } catch (Exception $exception) {
            $transaction->rollBack();
            $this->addError('cancelNotes', $exception->getMessage());
            return false;
        }
    }

    protected function deleteSalesLink($dto): void
    {

        // @Notes: unlink table if any (delete & create log);
        $salesLink = $this->repository->getSalesLink($dto->salesNum);
        if ($salesLink) {
            SalesLink::deleteAll(['linkSalesNum' => $dto->salesNum]);

            $dataSalesLink = $this->repository->getDataSalesLink($dto->salesNum);

            SalesLink::deleteAll(['salesNum' => $dto->salesNum]);

            $linkTableLog = [
                "tableID" => null,
                "mainSalesModel" => $this->salesModel,
                "salesLink" => $dataSalesLink
            ];

            $this->loggingRecord($dto->salesNum, Logging::LINK_TABLE,  $linkTableLog);
        }
    }

    protected function deleteSalesData($dto): void
    {
        $this->repository->deleteSalesHead($dto->salesNum, $dto->cancelNotes);
        $this->repository->deleteSalesMenu($dto->salesNum);
        $this->repository->deleteSalesExtra($dto->salesNum);
    }

    protected function validatestockMenuRTS($dto): void
    {
        // @notes : stock menu RTS
        $salesHead = $this->repository->getSalesHead($dto->salesNum);
        $salesMenus = $this->repository->getSalesMenu($dto->salesNum);
        if ($salesHead && $salesMenus) {
            foreach ($salesMenus as $salesMenu) {
                $qty = $salesMenu->qty;
                $isMenuPackage = $this->checkMenuPackage($salesMenu);
                if ($isMenuPackage) {
                    $salesHeadMenuPackages = $this->repository->getSalesHeadMenuPackage($salesMenu->salesNum, $salesMenu->menuRefID);
                    $qty = $qty * $salesHeadMenuPackages->qty;
                }

                $validateStockModel = new ValidateStock();
                $validateStockModel->salesNum = $salesMenu->salesNum;
                $validateStockModel->menuID = $salesMenu->menuID;
                $validateStockModel->qty = $qty;
                $validateStockModel->transactionModeID = $salesHead->transactionModeID;
                $validateStockModel->isCancelOrder = !in_array($salesMenu->statusID, [ 12, 19 ]);
                $validateStockModel->salesMenuID = $salesMenu->ID;
                $validateStockModel->category = 'Cancel Table';

                $result = $validateStockModel->validateStock();
                if($result){
                    throw new Exception(json_encode($result));
                }
            }
        }
    }

    protected function checkMenuPackage($salesMenu): bool{

        return  $salesMenu->menuRefID > 0 && $salesMenu->localID != $salesMenu->menuRefID;
    }

    /**
     * @param mixed $dto
     * @param mixed $eventSubject
     * @param mixed $modelAttr
     * @return void
     */
    protected function loggingRecord($dto, $eventSubject, $modelAttr): void
    {
        Logging::save( $dto->salesNum, $eventSubject, $modelAttr);
    }

}