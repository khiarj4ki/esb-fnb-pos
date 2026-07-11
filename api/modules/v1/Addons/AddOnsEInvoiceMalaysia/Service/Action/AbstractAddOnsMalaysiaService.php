<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service\Action;

use app\models\forms\Logging;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaDtoRequest;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto\AddOnsMalaysiaSubmitDocumentRequestDto;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Entity\Repository\AddOnsMalaysiaRepository;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaException;
use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception\AddOnsMalaysiaExceptionInterface;
use app\modules\v1\Addons\AddOnsEInvoiceMalaysia\Service\Action\contract\AbstractAddOnsMalaysiaServiceInterface;
use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\httpclient\Response;

abstract class AbstractAddOnsMalaysiaService implements AbstractAddOnsMalaysiaServiceInterface
{
    /**
     * @var AddOnsMalaysiaRepository
     */
    protected $repository;

    public function __construct(
        AddOnsMalaysiaRepository $addOnsMalaysiaRepository
    ) {
        $this->repository = $addOnsMalaysiaRepository;
    }

    /**
     * @param $dto
     * @return mixed
     */
    public function handle($dto)
    {
        try {
            $this->inputValidation($dto);
            $dto = $this->generateSetting($dto);
            $dto = $this->generateRequest($dto);
            $this->logRequest($dto);
            $dto = $this->httpRequest($dto);
            $this->logResponse($dto);
            if ($dto->isSuccess()) {
                return $this->handleSuccess($dto);
            }

            return $this->handleDecline($dto);

        } catch (Exception $exception) {
            return $this->handleError($exception, $dto);
        }
    }

    /**
     * @throws Exception
     */
    abstract function inputValidation($dto): void;

    /**
     * @param $dto
     * @return void
     */
    protected function logRequest($dto)
    {
        Logging::save($dto->getSalesNum(), Logging::GENERATE_INVOICE, $dto->getRequestBody());
    }

    /**
     * @param $dto
     * @return void
     */
    protected function logResponse($dto)
    {
        Logging::save($dto->getSalesNum(), Logging::GENERATE_INVOICE, $dto->getResponseBody());
    }

    /**
     * @throws Exception
     */
    protected function generateSetting($dto): AddOnsMalaysiaDtoRequest
    {
        if (!$dto instanceof AddOnsMalaysiaDtoRequest) {
            AddOnsMalaysiaException::error(AddOnsMalaysiaExceptionInterface::ERROR_INTERNAL_REQUEST_DTO);
        }

        $dto->setApiKey(
            $this->repository->getApiKey()
        );
        $dto->setApiUrl(
            $this->repository->findOrFailOMSUrlSetting(self::SETTING_ADD_ONS_LDHN_E_INVOICE_KEY_1, Yii::$app->params['omsServiceUrl'])
        );
        $dto->setBranchID(
            $this->repository->findOrFailBranchsettingID()
        );
        $dto->setBranchName(
            $this->repository->findOrFailBranch($dto->getBranchID())
        );

        return $dto;
    }

    /**
     * @param $dto
     * @param string $path
     * @param string $method
     * @return Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected function httpClientRequest($dto, string $path, string $method = 'POST'): Response
    {
        return (new Client())->createRequest()
            ->setUrl($dto->getApiUrl() . $path)
            ->setMethod($method)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $dto->getApiKey(),
            ])
            ->setData($dto->getRequestBody())
            ->setFormat(Client::FORMAT_JSON)
            ->send();
    }

    /**
     * @param $dto
     * @return mixed
     */
    abstract protected function generateRequest($dto);

    /**
     * @param $dto
     * @return mixed
     */
    abstract protected function httpRequest($dto);

    /**
     * @param $dto
     * @return mixed
     */
    abstract protected function handleDecline($dto);

    /**
     * @param $dto
     * @return mixed
     */
    abstract protected function handleSuccess($dto);

    /**
     * @param $dto
     * @return mixed
     */
    abstract protected function handleError(Exception $exception, $dto);

}