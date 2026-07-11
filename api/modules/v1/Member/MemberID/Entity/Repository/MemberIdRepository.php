<?php

namespace app\modules\v1\Member\MemberID\Entity\Repository;

use app\models\BrandSetting;
use app\models\Setting;
use Exception;
use Yii;

class MemberIdRepository implements MemberIdRepositoryInterface
{
    /**
     * @return string
     * @throws Exception
     */
    public function getApiKey(): string
    {
        return Setting::getApiKey();
    }

        /**
     * @return string
     * @throws Exception
     */
    public function getApiUrl(object $dto): string
    {
     
        $param = is_numeric($dto->search) && !strpos(strtoupper($dto->search), 'E') ? '?phoneNumber=' : '?memberCode=';
        $memberRequest = $param . $dto->search;
        $memberApiUrl = Yii::$app->security->decryptByKey(base64_decode($dto->getDataExternalMemberSetting()[self::GET_MEMBER_API_URL]), $this->getApiKey());
        return $memberApiUrl . $memberRequest;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getExternalMemberSetting(): array
    {
        return  BrandSetting::getExternalMemberSetting();
    }

}
