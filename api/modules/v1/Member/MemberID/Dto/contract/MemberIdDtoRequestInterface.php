<?php

namespace app\modules\v1\Member\MemberID\Dto\contract;

interface MemberIdDtoRequestInterface extends MemberIdDtoInterface
{
    const GET_TOKEN_MEMBER_API_URL = 'Get Token API Url';
    const GET_STATIC_TOKEN = 'Static Token';
    const GET_MEMBER_API_URL = 'Get Member API Url';
    const TRANSACTION_MEMBER_API_URL = 'Transaction Member API Url';
    const BURN_VOUCHER_API_URL = 'Burn Voucher API Url';
    const UNBURN_VOUCHER_API_URL = 'Unburn Voucher API Url';
    const MEMBER_ID_BRANCH_CODE = 'Member ID Branch Code';
    const GET_TOKEN_VOUCHER_API_URL = 'Get Token Voucher API Url';
    const BENEFIT_LIST_API_URL = 'Benefit List API URL';
    const BENEFIT_BURN_API_URL = 'Benefit Burn API URL';

    /**
     * @return string|null
     */
    public function getPhoneNumber(): ?string;

    /**
     * @return int|null
     */
    public function getPoint(): ?int;
}