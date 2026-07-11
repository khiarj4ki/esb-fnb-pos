<?php

namespace app\modules\v1\Member\MemberID\Service\Action;

use app\models\forms\Logging;
use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoInterface;
use app\modules\v1\Member\MemberID\Entity\Repository\MemberIdRepository;
use app\modules\v1\Member\MemberID\Service\Action\Contract\AbstractMemberIdActionServiceInterface;
use Exception;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\httpclient\Response;

abstract class AbstractMemberIdActionService implements AbstractMemberIdActionServiceInterface
{
    /**
     * @var MemberIdRepository $repository
     */
    protected $repository;

    /**
     * @param MemberIdRepository $memberIdRepository
     */
    public function __construct(
        MemberIdRepository $memberIdRepository
    ) {
        $this->repository = $memberIdRepository;
    }

    /**
     * @param MemberIdDtoInterface $request
     * @return mixed
     */
    public function handle(MemberIdDtoInterface $request)
    {
        try {
            $request->setExternalMemberSetting(
                $this->getExternalMemberSetting()
            );
            $request->setApiUrl(
                $this->getApiSetting($request)
            );

            $this->validate($request);

            $request->setRequestBody(
                $this->generateHttpRequestBody($request)
            );

            $request->setHttpResponse(
                $this->httpRequest($request)
            );

            if ($request->isSuccess()) {
                return $this->handleSuccess($request);
            }

            return $this->handleDecline($request);

        } catch (Exception $exception) {
            $this->logResponse($request);

            return $this->handleError($exception, $request);
        }
    }

    /**
     * @param MemberIdDtoInterface $request
     * @return void
     */
    protected function logResponse(MemberIdDtoInterface $request)
    {
        Logging::save($request->search, Logging::FAILED_GET_MEMBER , $request->getResponseBody());
    }

    /**
     * @throws Exception
     */
    protected function getExternalMemberSetting(): array
    {
        return $this->repository->getExternalMemberSetting();
    }

    /**
     * @param MemberIdDtoInterface $request
     * @return string
     * @throws Exception
     */
    protected function getApiSetting(MemberIdDtoInterface $request): string
    {
        return $this->repository->getApiUrl($request);

    }

    /**
     * @param string $url
     * @param array $body
     * @param string $token
     * @param string $method
     * @return Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected function httpClientRequest(string $url, array $body, string $token, string $method = 'POST'): Response
    {
        return (new Client())->createRequest()
            ->setUrl($url)
            ->setMethod($method)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mid-client-key' => $token,
            ])
            ->setData($body)
            ->setFormat(Client::FORMAT_JSON)
            ->setOptions([
                'timeout' => 300,
            ])
            ->send();
    }

    /**
     * @param MemberIdDtoInterface $request
     * @return bool
     */
    abstract protected function validate(MemberIdDtoInterface $request): bool;

    /**
     * @param MemberIdDtoInterface $request
     * @return array
     */
    abstract protected function generateHttpRequestBody(MemberIdDtoInterface $request): array;

    /**
     * @param MemberIdDtoInterface $request
     * @return mixed
     */
    abstract protected function httpRequest(MemberIdDtoInterface $request);

    /**
     * @param MemberIdDtoInterface $request
     * @return mixed
     */
    abstract protected function handleDecline(MemberIdDtoInterface $request);

    /**
     * @param MemberIdDtoInterface $request
     * @return mixed
     */
    abstract protected function handleSuccess(MemberIdDtoInterface $request);

    /**
     * @param Exception $exception
     * @param MemberIdDtoInterface $request
     * @return mixed
     */
    abstract protected function handleError(Exception $exception, MemberIdDtoInterface $request);

}