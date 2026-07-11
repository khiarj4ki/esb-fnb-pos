<?php

namespace app\modules\v1\Member\MemberID\Dto;

use app\modules\v1\Member\MemberID\Dto\contract\MemberIdDtoRequestInterface;
use Yii;

/**
 *
 * @property-read null|string $message
 * @property-read null|int $point
 * @property-read null|string|int $responseCode
 * @property-read null|string $phoneNumber
 * @property-read array $requestBody
 * @property-read array $dataExternalMemberSetting
 * @property-read string $dataApiUrl
 * @property-read array $dataResponse
 * @property-read mixed $httpRequest
 */
class MemberIdFetchDto extends AbstractMemberDto implements MemberIdDtoRequestInterface
{
    public $search;

    public $searchBy;

    /**
     * @return array[]
     */
    public function rules(): array
    {
        return [
            [['search', 'searchBy'], 'required'],
        ];
    }

    /**
     * @return int|null
     */
    public function getPoint(): ?int
    {
        return $this->getResponseBody()['data']['point'] ?? 0;
    }


    /**
     * @return array
     */
    public function transform(): array
    {
        if ($this->getErrors() || !$this->getHttpResponse()) {
            Yii::$app->response->statusCode = 400;
            $errors = $this->getErrors();
            $errorMessage = $errors[key($errors)][0] ?? 'Failed to Get Member';

            if(stripos($errorMessage, 'connection attempt failed')){
                $errorMessage = 'Loyalty did not respond in time.';
            }
            return [
                'status' => false,
                'time' => Date('c'),
                'code' => $this->getResponseCode() ?: false,
                'message' => $this->getMessage() ?: $errorMessage,
                'data' => null
            ];
        }

        $externalMemberSetting = $this->getExternalMemberSetting();

        return [
            'status' => true,
            'time' => Date('c'),
            'code' => $this->getResponseCode(),
            'message' => $this->getMessage() ?? 'success',
            'data' => $this->getDataResponse(),
            'phone' => $this->getPhoneNumber(),
            'cardID' => $this->search ?? null,
            'flagExternalAPI' => $externalMemberSetting['External Member'],
            'externalMembershipTypeID' => $externalMemberSetting['Membership Type'],
            'balance' => [
                'pointConversion'=> $externalMemberSetting['Point Conversion'],
                'totalAvailablePoints' => $this->getPoint()
            ],
            'id' => $this->getDataResult('memberCode'),
            'firstName' => $this->getDataResult('fullname'),
            'lastName' => '',
            'birthDate' => $this->getDataResult('dateOfBirth'),
            'membershipType' => $this->getDataResult('membershipType'),
            'email' => $this->getDataResult('email'),
            'point' => $this->getPoint(),
            'voucherMember' => [],
            'lastTransaction' => $this->getDataResult('lastTransaction') ?? [],
            'favoriteMenu' => $this->getDataResult('favoriteMenu') ?? [],
        ];
    }
}
