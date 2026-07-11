<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto;

use app\models\SalesHead;
use Exception;
use Yii;
use yii\httpclient\Response;

/**
 *
 * @property-write array $head
 * @property-read array $requestBody
 */
class AddOnsMalaysiaSubmitDocumentRequestDto extends AddOnsMalaysiaDtoRequest implements AddOnsMalaysiaSubmitDocumentRequestDtoInterface
{
    /**
     * @var ?string $salesNum
     */
    public $salesNum;

    /**
     * @var ?string $salesNum
     */
    public $buyer;

    /**
     * @var array $salesHead
     */
    private $salesHead;

    /**
     * @var array $salesMenu
     */
    private $salesMenu;

    /**
     * @var array $salesPayment
     */
    private $salesPayment;

    /**
     * @var string $uuid
     */
    private $uuid;

    /**
     * @var string $longID
     */
    private $longID;

    /**
     * @return array[]
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
            [['buyer'], 'validateBuyer'],
        ];
    }

    /**
     * @param $attribute
     * @param $params
     * @return void
     */
    public function validateBuyer($attribute, $params)
    {
        if ($this->$attribute) {
            $buyer = new AddOnsMalaysiaBuyer();
            $buyer->setAttributes($this->$attribute);

            if (!$buyer->validate()) {
                foreach ($buyer->errors as $key => $errors) {
                    $this->addError("$attribute.$key", implode(', ', $errors));
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function setSalesNum(string $salesNum)
    {
        $this->salesNum = $salesNum;
    }

    /**
     * @inheritdoc
     */
    public function getSalesNum(): string
    {
        return $this->salesNum;
    }

    public function setHead(array $salesHead)
    {
        $this->salesHead = $salesHead;
    }

    /**
     * @return array
     */
    public function getSalesHead(): array
    {
        return $this->salesHead;
    }

    /**
     * @param array $salesMenu
     */
    public function setSalesMenu(array $salesMenu): void
    {
        $this->salesMenu = $salesMenu;
    }

    /**
     * @return array
     */
    public function getSalesMenu(): array
    {
        return $this->salesMenu ?? [];
    }

    /**
     * @param array $salesPayment
     */
    public function setSalesPayment(array $salesPayment): void
    {
        $this->salesPayment = $salesPayment;
    }

    /**
     * @return array
     */
    public function getSalesPayment(): array
    {
        return $this->salesPayment;
    }

    /**
     * @return string|null
     */
    public function getBuyer(): ?array
    {
        return $this->buyer;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getRequestBody(): array
    {
        return [
            'salesNum' => $this->getSalesNum(),
            "branchID" => $this->getBranchID(),
            "branchName" => $this->getBranchName(),
            "buyer" => $this->getBuyer(),
            "salesHead" => $this->getSalesHead(),
            "salesMenu" => $this->getSalesMenu(),
            "salesPayment" => $this->getSalesPayment(),
            "createdBy" => self::CREATED
        ];
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
     * @return string
     */
    public function getUuid(): string
    {
        return $this->getResponseBody()['uuid'] ?? '';
    }

    /**
     * @return string
     */
    public function getLongID(): string
    {
        return $this->getResponseBody()['longID'] ?? '';
    }

    /**
     * @return string[]
     */
    public function getDataResponse(): array
    {
        return $this->getResponseBody()['result'] ?? [];
    }

}