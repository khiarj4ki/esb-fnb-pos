<?php

namespace app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract;

interface AddOnsMalaysiaInquiryDocumentActionInterface extends AbstractAddOnsMalaysiaServiceInterface
{
    const HTTP_PATH = '/oms/invoice';
    const HTTP_METHOD = 'GET';
}