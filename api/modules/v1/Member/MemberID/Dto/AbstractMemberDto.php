<?php

namespace app\modules\v1\Member\MemberID\Dto;

use app\models\BrandSetting;
use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoInterface;
use app\modules\v1\Member\MemberID\Entity\Model\MemberId;
use app\modules\v1\Member\MemberID\Entity\Repository\MemberIdRepositoryInterface;
use app\modules\v1\Member\MemberID\Exception\MemberIDException;
use app\modules\v1\Member\MemberID\Exception\MemberIDExceptionInterface;
use Exception;
use Yii;
use yii\httpclient\Response;

/**
 *
 * @property-read null|string $responseCode
 * @property-read null|string $message
 * @property-read array $dataResponse
 * @property-read string $dataApiUrl
 * @property-read null|string $phoneNumber
 * @property-read array $dataExternalMemberSetting
 */
abstract class AbstractMemberDto extends MemberId implements MemberIdDtoInterface
{
    /**
     * @var string $apiKey
     */
    protected $apiKey;

    /**
     * @var string $apiUrl
     */
    protected $apiUrl;

    /**
     * @var array $externalMemberSetting
     */
    protected $externalMemberSetting;

    /**
     * @var Response $httpResponse
     */
    protected $httpResponse;

    /**
     * @var array $responseBody
     */
    protected $responseBody;

    /**
     * @var array $requestBody
     */
    protected $requestBody = [];

    /**
     * @param string $apiKey
     * @return void
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $apiUrl
     * @return void
     */
    public function setApiUrl(string $apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @param array $externalMemberSetting
     * @return void
     */
    public function setExternalMemberSetting(array $externalMemberSetting): void
    {
        $this->externalMemberSetting = $externalMemberSetting;
    }

    /**
     * @return array
     */
    public function getDataExternalMemberSetting(): array
    {
        return $this->externalMemberSetting;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function findOrFailedStaticToken(): string
    {
        if (!isset($this->externalMemberSetting[MemberIdRepositoryInterface::GET_STATIC_TOKEN])) {
            MemberIDException::error(MemberIDExceptionInterface::SETTING_STATIC_TOKEN_NOT_FOUND);
        }
        return $this->externalMemberSetting[MemberIdRepositoryInterface::GET_STATIC_TOKEN];
    }

    /**
     * @param array $responseBody
     * @return void
     */
    public function setResponseBody(array $responseBody): void
    {
        $this->responseBody = $responseBody;
    }

    /**
     * @return array
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * @return string|null
     */
    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    /**
     * @param Response $httpResponse
     * @return void
     */
    public function setHttpResponse(Response $httpResponse): void
    {
        $this->httpResponse = $httpResponse;

        $this->setResponseBody(
            json_decode($httpResponse->getContent(), true)
        );
    }

    /**
     * @return Response
     */
    public function getHttpResponse(): Response
    {
        return $this->httpResponse;
    }

    /**
     * @return string
     */
    public function getDataApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function setRequestBody($requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    /**
     * @return array
     */
    public function getRequestBody(): array
    {
        return $this->requestBody;
    }

    /**
     * @return array
     */
    public function getDataResponse(): array
    {
        return $this->getResponseBody()['data'] ?? [];
    }

    /**
     * @return bool
     * @throws \yii\httpclient\Exception
     */
    public function isSuccess(): bool
    {
        return $this->getHttpResponse()->getIsOk();
    }

    /**
     * @return array
     */
    public function getExternalMemberSetting(): ?array
    {
        return BrandSetting::getExternalMemberSetting();
    }

    /**
     * @return string|null
     */
    public function getPhoneNumber(): ?string
    {
        $phoneNumber = $this->getResponseBody()['data']['phoneNumber'] ?? null;
        //@notes: formatting phone number
        if (substr($phoneNumber, 0, 1) === '+') {
            $phoneNumber = substr($phoneNumber, 1);
        } 
        return $phoneNumber;
    }

    /**
     * @return string|null
     */
    public function getResponseCode(): ?string
    {
        return $this->getResponseBody()['statusCode'] ?? 400;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->getResponseBody()['message'] ?? null;
    }

    /**
     * @param string $params
     * @return string|null
     */
    public function getDataResult(string $params): ?string
    {
        return $this->getResponseBody()['data'][$params] ?? null;
    }
}