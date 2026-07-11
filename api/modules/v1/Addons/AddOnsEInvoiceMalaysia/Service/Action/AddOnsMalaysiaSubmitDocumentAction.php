<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service\Action;

use app\models\forms\Logging;
use app\models\Setting;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaSubmitDocumentRequestDto;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaException;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaExceptionInterface;
use app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract\AddOnsMalaysiaSubmitDocumentActionInterface;
use Exception;
use Yii;
use yii\base\InvalidConfigException;

class AddOnsMalaysiaSubmitDocumentAction extends AbstractAddOnsMalaysiaService implements AddOnsMalaysiaSubmitDocumentActionInterface
{
    /**
     * @param $dto
     * @return void
     * @throws Exception
     */
    function inputValidation($dto): void
    {
        if (!$dto instanceof AddOnsMalaysiaSubmitDocumentRequestDto){
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        if(!Setting::getValue1(self::SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_1, self::SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_2)){
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::DECLINE_SETTING_ADD_ONS_NOT_FOUND);
        }
    }

    /**
     * @throws Exception
     */
    protected function generateRequest($dto): AddOnsMalaysiaSubmitDocumentRequestDto
    {
        if (!$dto instanceof AddOnsMalaysiaSubmitDocumentRequestDto){
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        $dto->setHead(
            $this->repository->findOrFailSalesHead($dto->getSalesNum())
        );
        $dto->setSalesPayment(
            $this->repository->getSalesPayment($dto->getSalesNum())
        );
        $dto->setSalesMenu(
            $this->repository->getSalesMenu($dto->getSalesNum())
        );

        return $dto;

    }

    /**
     * @throws \yii\httpclient\Exception
     * @throws InvalidConfigException
     * @throws Exception
     */
    protected function httpRequest($dto): AddOnsMalaysiaSubmitDocumentRequestDto
    {
        if (!$dto instanceof AddOnsMalaysiaSubmitDocumentRequestDto) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        $dto->setHttpResponse(
            $this->httpClientRequest($dto, self::HTTP_PATH, self::HTTP_METHOD)
        );

        return $dto;
    }

    /**
     * @param $dto
     * @return mixed
     * @throws Exception
     */
    protected function handleDecline($dto)
    {
        if (!$dto instanceof AddOnsMalaysiaSubmitDocumentRequestDto) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }
        $dto->addError($dto->getResponseCode(), $dto->getMessage());

        return $dto;
    }

    /**
     * @param $dto
     * @return AddOnsMalaysiaSubmitDocumentRequestDto
     * @throws Exception
     */
    protected function handleSuccess($dto): AddOnsMalaysiaSubmitDocumentRequestDto
    {
        if (!$dto instanceof AddOnsMalaysiaSubmitDocumentRequestDto) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        return $dto;
    }

    /**
     * @param Exception $exception
     * @param $dto
     * @return AddOnsMalaysiaSubmitDocumentRequestDto
     */
    protected function handleError(Exception $exception, $dto = null): AddOnsMalaysiaSubmitDocumentRequestDto
    {
        Yii::error($exception);

        $ref = $dto instanceof AddOnsMalaysiaSubmitDocumentRequestDto ? $dto->getSalesNum() : null;
        Logging::save($ref, Logging::GENERATE_INVOICE, [
            'errMsg' => $exception->getMessage()
        ]);

        $dto->addError($exception->getCode(), $exception->getMessage());

        return $dto;
    }
}