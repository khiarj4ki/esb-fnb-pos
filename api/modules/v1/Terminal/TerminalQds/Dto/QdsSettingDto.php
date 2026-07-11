<?php

namespace app\modules\v1\Terminal\TerminalQds\Dto;

use yii\base\Model;

class QdsSettingDto extends Model
{
    public $visitPurposeIds;
    public $additionalInfo;
    public $showTableInfo;
    public $stationID;
    public $activeStation;
    public $selectedLanguage;
    public $terminalID;

    public function rules(): array
    {
        return [
            [['additionalInfo', 'showTableInfo', 'activeStation', 'selectedLanguage'], 'integer'],
            [['visitPurposeIds', 'terminalID', 'stationID'], 'string', 'skipOnEmpty' => true], 
        ];
    }

    public function qdsValue(): array
    {
        return [
            Constants::TERMINAL_QDS_SALES_MODE  => $this->visitPurposeIds,
            Constants::TERMINAL_QDS_SHOW_ADDITIONAL_INFO  => $this->additionalInfo,
            Constants::TERMINAL_QDS_SHOW_TABLE_NUMBER => $this->showTableInfo,
            Constants::TERMINAL_QDS_STATIONS => $this->stationID,
            Constants::TERMINAL_QDS_ACTIVE_STATION => $this->activeStation,
            Constants::TERMINAL_QDS_LANGUAGE => $this->selectedLanguage
        ];
    }
}