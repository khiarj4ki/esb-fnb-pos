<?php


namespace app\modules\v1\Member\MemberID\Service;

use app\modules\v1\Member\MemberID\Dto\MemberIdFetchDto;

interface MemberIdServiceInterface
{
    /**
     * @param array $input
     * @return MemberIdFetchDto
     */
    public function fetchMember(array $input): MemberIdFetchDto;

}