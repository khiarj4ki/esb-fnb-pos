<?php

namespace app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract;

interface AddOnsMalaysiaSubmitDocumentActionInterface extends AbstractAddOnsMalaysiaServiceInterface
{
    const HTTP_PATH = '/oms/invoice/submit';
    const HTTP_METHOD = 'POST';
}