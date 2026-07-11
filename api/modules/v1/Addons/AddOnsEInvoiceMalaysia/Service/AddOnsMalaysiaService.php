<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service;

use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaInquiryDocumentRequest;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaSubmitDocumentRequestDto;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service\Action\AddOnsMalaysiaInquiryDocumentAction;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service\Action\AddOnsMalaysiaSubmitDocumentAction;

class AddOnsMalaysiaService implements AddOnsMalaysiaServiceInterface
{
    /**
     * @var AddOnsMalaysiaSubmitDocumentAction
     */
    private $submitDocumentAction;
    /**
     * @var AddOnsMalaysiaInquiryDocumentAction
     */
    private $inquiryDocumentAction;
    /**
     * @var AddOnsMalaysiaSubmitDocumentRequestDto
     */
    private $submitDocumentRequestDto;

    /**
     * @param AddOnsMalaysiaSubmitDocumentAction $submitDocumentAction
     */
    public function __construct(
        AddOnsMalaysiaSubmitDocumentRequestDto $submitDocumentRequestDto,
        AddOnsMalaysiaSubmitDocumentAction $submitDocumentAction,
        AddOnsMalaysiaInquiryDocumentAction $inquiryDocument
    ) {
        $this->submitDocumentRequestDto = $submitDocumentRequestDto;
        $this->submitDocumentAction = $submitDocumentAction;
        $this->inquiryDocumentAction = $inquiryDocument;
    }

    /**
     * @param array $input
     * @return AddOnsMalaysiaSubmitDocumentRequestDto
     */
    public function submitDocument(array $input): AddOnsMalaysiaSubmitDocumentRequestDto
    {
        $this->submitDocumentRequestDto->setAttributes($input);
        if (!$this->submitDocumentRequestDto->validate()) {
            return $this->submitDocumentRequestDto;
        }

        return $this->submitDocumentAction->handle($this->submitDocumentRequestDto);
    }

    /**
     * @param array $input
     * @return AddOnsMalaysiaInquiryDocumentRequest
     */
    public function inquiryDocument(array $input): AddOnsMalaysiaInquiryDocumentRequest
    {
        /**
         * request validation
         */
        $dto = new AddOnsMalaysiaInquiryDocumentRequest();
        $dto->setAttributes($input);
        if (!$dto->validate()) {
            return $dto;
        }

        return $this->inquiryDocumentAction->handle($dto);
    }
}