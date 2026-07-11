<?php

namespace app\modules\v1\Member\Dto;

use app\models\forms\BookTable;

/**
 *
 * @property bool $dataResponse
 */
class UpdateMemberRequest extends BookTable
{
    /**
     * @var bool
     */
    protected $data = false;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['tableID', 'visitPurposeID', 'paxTotal'], 'required'],
            [['tableID', 'memberID', 'visitPurposeID', 'paxTotal', 'flagInclusive', 'visitorTypeID'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
            [['memberID'], 'validateMember'],
            [['visitPurposeID'], 'validateVisitPurpose'],
            [['externalMemberName'], 'validateExternalMemberName'],
            [[
                'externalMembershipTypeID','flagExternalAPI','flagExternalMemberID','flagExternalMemberPhone','flagExternalCardID',
                'employeeCode','employeeType','employeeName','orderTimeOut','memberCode','bookNum','externalMemberName',
                'externalTransID','questionAnswers'
            ],'safe']
        ];
    }

    /**
     * @param bool $data
     * @return void
     */
    public function setDataResponse(bool $data)
    {
        $this->data = $data;
    }

    /**
     * @return bool
     */
    public function getDataResponse(): bool
    {
        return $this->data;
    }

    /**
     * @return bool
     */
    public function transform(): bool
    {
        return $this->getDataResponse();
    }
}