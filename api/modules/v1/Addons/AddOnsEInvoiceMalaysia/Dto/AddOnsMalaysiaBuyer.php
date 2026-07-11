<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Dto;

use yii\base\Model;

class AddOnsMalaysiaBuyer extends Model
{
    public $buyerName;
    public $buyerTin;
    public $buyerIdType;
    public $buyerIdNum;
    public $buyerCity;
    public $buyerState;
    public $buyerAddress;
    public $buyerContact;
    public $buyerSst;

    public function rules(): array
    {
        return [
            [['buyerName'], 'required'],
            [['buyerName', 'buyerTin', 'buyerIdType', 'buyerIdNum', 'buyerCity', 'buyerState', 'buyerAddress', 'buyerContact', 'buyerSst'], 'string', 'max' => 255],
        ];
    }
}