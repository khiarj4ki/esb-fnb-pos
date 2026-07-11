<?php
namespace app\modules\v1\Member\Service;


use app\modules\v1\Member\Dto\UpdateMemberRequest;
use app\modules\v1\Member\Service\Action\MemberUpdateAction;

class MemberService implements MemberServiceInterface
{
    /**
     * @var UpdateMemberRequest
     */
    protected $updateMemberRequest;
    /**
     * @var MemberUpdateAction
     */
    protected $updateAction;

    /**
     * @param UpdateMemberRequest $updateMemberRequest
     * @param MemberUpdateAction $updateAction
     */
    public function __construct(
        UpdateMemberRequest $updateMemberRequest,
        MemberUpdateAction $updateAction
    ) {
        $this->updateMemberRequest = $updateMemberRequest;
        $this->updateAction = $updateAction;
    }

    /**
     * @inheritdoc
     */
    /**/
    public function updateMember(array $input)
    {
        $this->updateMemberRequest->setAttributes($input);
        if (!$this->updateMemberRequest->validate()) {
            return $this->updateMemberRequest;
        }

        return $this->updateAction->handle($this->updateMemberRequest);

    }
}
