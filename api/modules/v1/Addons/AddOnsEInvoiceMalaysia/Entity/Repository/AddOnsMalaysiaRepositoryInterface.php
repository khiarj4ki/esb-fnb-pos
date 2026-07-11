<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Entity\Repository;

interface AddOnsMalaysiaRepositoryInterface
{
    /**
     * @param string $salesNum
     * @return array
     */
    public function findOrFailSalesHead(string $salesNum): array;

    /**
     * @param string $salesNum
     * @return array
     */
    public function getSalesPayment(string $salesNum): array;

    /**
     * @param string $salesNum
     * @return array
     */
    public function getSalesMenu(string $salesNum): array;

}