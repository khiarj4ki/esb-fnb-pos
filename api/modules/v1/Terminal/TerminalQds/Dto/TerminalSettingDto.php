<?php
namespace app\modules\v1\Terminal\TerminalQds\Dto;

class TerminalSettingDto {
    public $visitPurposeIds;
    public $additionalInfo;
    public $showTableInfo;
    public $stationID;
    public $activeStation;
    public $selectedLanguage;
    public $terminalID;

    public function __construct(array $data) {
        $this->visitPurposeIds = $data['visitPurposeIds'] ?? null;
        $this->additionalInfo = isset($data['additionalInfo']) ? (int) $data['additionalInfo'] : 0;
        $this->showTableInfo = isset($data['showTableInfo']) ? (int) $data['showTableInfo'] : 0;
        $this->stationID = $data['stationID'] ?? null;
        $this->activeStation = isset($data['activeStation']) ? (int) $data['activeStation'] : 0;
        $this->selectedLanguage = isset($data['selectedLanguage']) ? (int) $data['selectedLanguage'] : 1;
        $this->terminalID = isset($data['terminalID']) ? (int) $data['terminalID'] : 0;
    }
}