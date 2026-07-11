<?php

namespace app\modules\v1\Member\Service\Action;

use app\modules\v1\Member\Dto\UpdateMemberRequest;

interface MemberUpdateActionInterface
{
    /**
     * @param UpdateMemberRequest $request
     * @return UpdateMemberRequest
     */
    public function handle(UpdateMemberRequest $request): UpdateMemberRequest;
}