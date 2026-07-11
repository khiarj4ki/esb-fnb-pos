<?php

namespace app\modules\v1\Member\Entity\Model;

use app\models\forms\BookTable;
use app\models\SalesHead;
use app\models\VisitPurpose;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $memberID
 * @property int $memberCode
 * @property int $visitPurposeID
 * @property int $paxTotal
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 * @property VisitPurpose $visitModel
 */
class UpdateMember extends BookTable
{

}
