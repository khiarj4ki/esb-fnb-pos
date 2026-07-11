<?php

namespace app\modules\v1\Member\MemberID;

use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoRequestInterface;
use app\modules\v1\Member\MemberID\Dto\MemberIdFetchDto;
use Yii;

class MemberIDProvider
{
    public static function register()
    {
        Yii::$container->set(
            MemberIdDtoRequestInterface::class,
            MemberIdFetchDto::class
        );
    }
}