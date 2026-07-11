<?php

namespace app\modules\v1\Member\Entity\Repository;

interface MemberSalesRepositoryInterface
{
    /**
     * @param $tableId
     * @param string $salesNum
     * @return mixed
     */
    public static function findOutStandingFullService($tableId, string $salesNum);

    /**
     * @param string $salesNum
     * @return mixed
     */
    public static function findOutStandingQuickService(string $salesNum);
}