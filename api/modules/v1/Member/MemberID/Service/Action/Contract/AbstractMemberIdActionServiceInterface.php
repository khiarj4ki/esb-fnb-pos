<?php

namespace app\modules\v1\Member\MemberID\Service\Action\Contract;

use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoInterface;

interface AbstractMemberIdActionServiceInterface
{
    const HTTP_PATH = '/oms/invoice/submit';
    const HTTP_GET_METHOD = 'GET';

    public function handle(MemberIdDtoInterface $request);

}