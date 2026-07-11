<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto;

use app\models\SalesHead;

/**
 *
 * @property-read string $queryString
 * @property-read array $requestBody
 */
class AddOnsMalaysiaInquiryDocumentRequest extends AddOnsMalaysiaDtoRequest
{
    /**
     * @var string $salesNum
     */
    public $salesNum = '';

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['salesNum'], 'required'],
            [['salesNum'], 'string', 'max' => 25],
            ['salesNum', 'exist',
                'targetClass' => SalesHead::class,
                'targetAttribute' => 'salesNum',
                'message' => 'sales number not found',
            ],
        ];
    }

    /**
     * @return string
     */
    public function getSalesNum(): string
    {
        return $this->salesNum ?? '';
    }

    /**
     * @return array
     */
    public function getRequestBody(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getPathParameter(): string
    {
        return '/' . $this->getBranchID() . '/' . $this->salesNum;
    }

    /**
     * @return array
     */
    function getDataResponse(): array
    {
        $responseBody = $this->getResponseBody();
        return isset($responseBody['result']) && isset($responseBody['result']['result']) 
            ? $responseBody['result']['result'] 
            : [];
    }
}