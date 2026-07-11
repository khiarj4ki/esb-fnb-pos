<?php

namespace app\modules\v1\Member\MemberID\Service\Action;


use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoInterface;
use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoRequestInterface;
use app\modules\v1\Member\MemberID\Dto\MemberIdFetchDto;
use app\modules\v1\Member\MemberID\Exception\MemberIDException;
use app\modules\v1\Member\MemberID\Exception\MemberIDExceptionInterface;
use Exception;
use yii\base\InvalidConfigException;
use yii\httpclient\Response;

class MemberIdAction extends AbstractMemberIdActionService
{
    /**
     * @var int $attempts
     */
    protected $attempts = 1;
    const MAX_RETRY_ATTEMPTS = 2;
    const HTTP_CODE_RETRY = 401;

    /**
     * @param $request
     * @return bool
     */
    protected function validate($request): bool
    {
        return true;
    }

    /**
     * @param $request
     * @return array
     * @throws Exception
     */
    protected function generateHttpRequestBody($request): array
    {
        return [];
    }

    /**
     * @param MemberIdDtoInterface $request
     * @return Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected function httpRequest(MemberIdDtoInterface $request): Response
    {
        return $this->httpClientRequest(
            $request->getDataApiUrl(),
            $request->getRequestBody(),
            $request->findOrFailedStaticToken(),
            self::HTTP_GET_METHOD
        );
    }

    /**
     * @param $request
     * @return mixed
     * @throws Exception
     */
    protected function handleDecline($request)
    {
        if (!$request instanceof MemberIdFetchDto) {
            MemberIDException::error(MemberIDExceptionInterface::DTO_INVALID);
        }
        throw new Exception($request->getResponseBody()['message'], 400);
    }

    /**
     * @param MemberIdDtoInterface $request
     * @return MemberIdFetchDto
     */
    protected function handleSuccess(MemberIdDtoInterface $request): MemberIdDtoRequestInterface
    {
        return $request;
    }

    /**
     * @param Exception $exception
     * @param MemberIdDtoInterface $request
     * @return MemberIdDtoInterface
     */
    protected function handleError(Exception $exception, MemberIdDtoInterface $request): MemberIdDtoInterface
    {
        $this->attempts ++;

        if ($exception->getCode() == self::HTTP_CODE_RETRY && $this->attempts < self::MAX_RETRY_ATTEMPTS - 1) {
            // Refresh the access token and retry.
            $this->handle($request);

            sleep(1);
        }

        $request->addError($exception->getCode(), $exception->getMessage());

        return $request;
    }

}