<?php

namespace app\modules\v1\Member\MemberID\Service;

use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoRequestInterface;
use app\modules\v1\Member\MemberID\Dto\MemberIdFetchDto;
use app\modules\v1\Member\MemberID\Service\Action\MemberIdAction;

class MemberIdService implements MemberIdServiceInterface
{
    /**
     * @var MemberIdAction
     */
    private $fetchMemberAction;
    /**
     * @var MemberIdDtoRequestInterface $fetchMemberRequestDto
     */
    private $fetchMemberRequestDto;

    /**
     * @param MemberIdFetchDto $fetchMemberRequestDto
     * @param MemberIdAction $fetchMemberAction
     */
    public function __construct(
        MemberIdDtoRequestInterface $fetchMemberRequestDto,
        MemberIdAction   $fetchMemberAction
    ) {
        $this->fetchMemberRequestDto  = $fetchMemberRequestDto;
        $this->fetchMemberAction = $fetchMemberAction;
    }

    /**
     * @param array $input
     * @return MemberIdFetchDto
     * @throws \Exception
     */
    public function fetchMember(array $input): MemberIdFetchDto
    {
        $this->fetchMemberRequestDto->setAttributes($input);
        if (!$this->fetchMemberRequestDto->validate()) {
            return $this->fetchMemberRequestDto;
        }

        return $this->fetchMemberAction->handle($this->fetchMemberRequestDto);
    }
}
