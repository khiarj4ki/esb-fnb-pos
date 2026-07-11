<?php

namespace app\modules\v1\Member\MemberID\Dto\contract;

use yii\httpclient\Response;

interface MemberIdDtoInterface
{
    /**
     * @param string|null $apiKey
     * @return void
     */
    public function setApiKey(string $apiKey): void;

    /**
     * @return string|null
     */
    public function getApiKey(): ?string;

    /**
     * @param string|null $apiUrl
     * @return void
     */
    public function setApiUrl(string $apiUrl): void;

    /**
     * @return string|null
     */
    public function getApiUrl(): ?string;

    /**
     * @param array $externalMemberSetting
     * @return void
     */
    public function setExternalMemberSetting(array $externalMemberSetting): void;

    /**
     * @return array
     */
    public function getDataExternalMemberSetting(): array;

    /**
     * @return string
     */
    public function findOrFailedStaticToken(): string;

    /**
     * @param array $requestBody
     * @return void
     */
    public function setRequestBody(array $requestBody): void;

    /**
     * @return array
     */
    public function getRequestBody(): array;

    /**
     * @return array
     */
    public function getDataResponse(): array;

    /**
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * @return array|null
     */
    public function getExternalMemberSetting(): ?array;

    /**
     * @return string|null
     */
    public function getResponseCode(): ?string;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * @param string $params
     * @return string|null
     */
    public function getDataResult(string $params): ?string;

    /**
     * @param Response $httpResponse
     * @return void
     */
    public function setHttpResponse(Response $httpResponse): void;

    /**
     * @return Response|null
     */
    public function getHttpResponse(): ?Response;

    /**
     * @param array $responseBody
     * @return void
     */
    public function setResponseBody(array $responseBody): void;

    /**
     * @return array|null
     */
    public function getResponseBody(): ?array;

    /**
     * @return array
     */
    public function transform(): array;
}
