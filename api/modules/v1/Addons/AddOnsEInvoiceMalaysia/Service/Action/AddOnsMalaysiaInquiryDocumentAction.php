<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service\Action;

use app\models\forms\Logging;
use app\models\Setting;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaInquiryDocumentRequest;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaException;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaExceptionInterface;
use app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract\AbstractAddOnsMalaysiaServiceInterface;
use app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract\AddOnsMalaysiaInquiryDocumentActionInterface;
use Exception;
use Yii;
use yii\base\InvalidConfigException;

class AddOnsMalaysiaInquiryDocumentAction extends AbstractAddOnsMalaysiaService implements AddOnsMalaysiaInquiryDocumentActionInterface
{

    function inputValidation($dto): void
    {
        if (!$dto instanceof AddOnsMalaysiaInquiryDocumentRequest) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        if(!Setting::getValue1(AbstractAddOnsMalaysiaServiceInterface::SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_1, AbstractAddOnsMalaysiaServiceInterface::SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_2)){
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::DECLINE_SETTING_ADD_ONS_NOT_FOUND);
        }
    }

    /**
     * @param $dto
     * @return mixed
     */
    protected function generateRequest($dto)
    {
        return $dto;
    }

    /**
     * @param $dto
     * @return AddOnsMalaysiaInquiryDocumentRequest
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     * @throws Exception
     */
    protected function httpRequest($dto): AddOnsMalaysiaInquiryDocumentRequest
    {
        if (!$dto instanceof AddOnsMalaysiaInquiryDocumentRequest) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        $dto->setHttpResponse(
            $this->httpClientRequest($dto, self::HTTP_PATH . $dto->getPathParameter(), self::HTTP_METHOD)
        );

        return $dto;
    }

    /**
     * @throws Exception
     */
    protected function handleDecline($dto)
    {
        // TODO: mapping error message decline from oms service
        AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::DECLINE_HTTP_SUBMIT_DOCUMENT);
    }

    /**
     * @throws Exception
     */
    protected function handleSuccess($dto)
    {
        if (!$dto instanceof AddOnsMalaysiaInquiryDocumentRequest) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        return $dto;
    }

    /**
     * @param Exception $exception
     * @param $dto
     * @return AddOnsMalaysiaInquiryDocumentRequest
     */
    protected function handleError(Exception $exception, $dto = null): AddOnsMalaysiaInquiryDocumentRequest
    {
        Yii::error($exception);

        $ref = $dto instanceof AddOnsMalaysiaInquiryDocumentRequest ? $dto->getSalesNum() : null;
        Logging::save($ref, Logging::CHECK_INQUIRY_INVOICE, [
            'errMsg' => $exception->getMessage()
        ]);

        $dto->addError($exception->getCode(), $exception->getMessage());

        return $dto;
    }

}