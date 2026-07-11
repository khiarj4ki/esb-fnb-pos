<?php
namespace app\modules\v1\Member\Service;
interface MemberServiceInterface
{
    /**
     * @param array $input
     * @return mixed
     */
    public function updateMember(array $input);
}