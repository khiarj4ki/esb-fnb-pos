<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto;

use app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Dto\contract\AddOnsMalaysiaDtoRequestInterface;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaException;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaExceptionInterface;
use Exception;
use yii\base\Model;
use yii\httpclient\Response;

/**
 *
 * @property-read null|string $message
 * @property-read null $responseCode
 * @property-read array $dataResponse
 */
abstract class AddOnsMalaysiaDtoRequest extends Model implements AddOnsMalaysiaDtoRequestInterface
{
    const HTTP_STATUS_CODE_OK = 'ok';

    /**
     * @var string $apiKey
     */
    protected $apiKey;

    /**
     * @var string $apiUrl
     */
    protected $apiUrl;

    /**
     * @var int $branchID
     */
    protected $branchID;

    /**
     * @var string$branchName
     */
    protected $branchName;

    /**
     * @var Response $httpResponse
     */
    protected $httpResponse;

    public $addOns;

    /**
     * @var array $responseBody
     */
    protected $responseBody;

    /**
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $apiUrl
     */
    public function setApiUrl(string $apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @param int $branchID
     */
    public function setBranchID(int $branchID): void
    {
        $this->branchID = $branchID;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * @return int
     */
    public function getBranchID(): int
    {
        return $this->branchID;
    }

    /**
     * @param string $branchName
     */
    public function setBranchName(string $branchName): void
    {
        $this->branchName = $branchName;
    }

    /**
     * @return string
     */
    public function getBranchName(): string
    {
        return $this->branchName ?? "ESB Branch";
    }

    /**
     * @return array
     */
    abstract function getDataResponse(): array;

    /**
     * @param Response $httpResponse
     * @return void
     * @throws Exception
     */
    public function setHttpResponse(Response $httpResponse)
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
     * @param array $responseBody
     * @throws Exception
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
     * @return bool
     * @throws \yii\httpclient\Exception
     */
    public function isSuccess(): bool
    {
        return $this->getHttpResponse()->getIsOk();
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->getResponseBody()['message'] ?? null;
    }

    /**
     * @return null
     */
    public function getResponseCode()
    {
        return $this->getResponseBody()['code'] ?? null;
    }

    /**
     * @return array
     */
    public function transform(): array
    {
        /**
         * Error request validations
         *
         */
        if ($this->getErrors()) {
            return [
                'status' => false,
                'time' => Date('c'),
                'code' => $this->getResponseCode() ??(string) AddOnsMalaysiaExceptionInterface::ERROR_REQUEST_VALIDATION,
                'message' =>  $this->getMessage() ?? AddOnsMalaysiaException::getErrorMessage(AddOnsMalaysiaExceptionInterface::ERROR_REQUEST_VALIDATION),
                'result' => $this->getErrors()
            ];
        }

        return [
            'status' => true,
            'time' => Date('c'),
            'code' => $this->getResponseCode(),
            'message' => $this->getMessage() ?? self::MESSAGE_SUCCESS,
            'result' => $this->getDataResponse(),
        ];
    }


}