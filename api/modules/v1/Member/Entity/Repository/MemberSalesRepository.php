<?php

namespace app\modules\v1\Member\Entity\Repository;

use app\models\SalesHead;
use app\models\SalesMergeTable;

class MemberSalesRepository implements MemberSalesRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public static function findOutStandingFullService($tableId, string $salesNum)
    {
        return SalesHead::findOutstanding()
            ->joinWith('salesMergeTables')
            ->andWhere([
                'OR',
                [SalesHead::tableName() . '.tableID' => $tableId],
                [SalesMergeTable::tableName() . '.tableID' => $tableId],
            ])
            ->andFilterWhere([SalesHead::tableName() . '.salesNum' => $salesNum])
            ->one();
    }

    /**
     * @inheritdoc
     */
    public static function findOutStandingQuickService(string $salesNum)
    {
        return SalesHead::findOutstanding()
            ->andWhere(['salesNum' =>$salesNum])
            ->one();
    }


}