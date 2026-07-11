<?php

namespace app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract;

interface AbstractAddOnsMalaysiaServiceInterface
{
    const SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_1 = 'POS';
    const SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_2 = 'LHDN eInvoice';

    public function handle($dto);
}