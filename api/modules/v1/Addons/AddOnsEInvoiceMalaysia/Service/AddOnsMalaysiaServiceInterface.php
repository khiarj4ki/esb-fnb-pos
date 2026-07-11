<?php


namespace app\modules\v1\addons\AddonsEInvoiceMalaysia\Service;

use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaInquiryDocumentRequest;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaSubmitDocumentRequestDto;

interface AddOnsMalaysiaServiceInterface
{
    /**
     * @param array $input
     * @return AddOnsMalaysiaSubmitDocumentRequestDto
     */
    public function submitDocument(array $input): AddOnsMalaysiaSubmitDocumentRequestDto;

    /**
     * @param array $input
     * @return AddOnsMalaysiaInquiryDocumentRequest
     */
    public function inquiryDocument(array $input): AddOnsMalaysiaInquiryDocumentRequest;
}