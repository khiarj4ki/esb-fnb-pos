<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto;

use app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Dto\contract\AddOnsMalaysiaDtoRequestInterface;

interface AddOnsMalaysiaSubmitDocumentRequestDtoInterface extends AddOnsMalaysiaDtoRequestInterface
{
    const CREATED = 'POS';

    /**
     * @param string $salesNum
     * @return void
     */
    public function setSalesNum(string $salesNum);

    /**
     * @return string
     */
    public function getSalesNum(): string;

}