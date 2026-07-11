<?php

namespace app\modules\v1\Terminal\TerminalQds\Service;

use app\models\forms\Logging;
use app\modules\v1\Terminal\TerminalQds\Dto\QdsSettingDto;
use app\modules\v1\Terminal\TerminalQds\Dto\Constants;
use app\modules\v1\Terminal\TerminalQds\Entity\Repository\TerminalRepository;
use app\modules\v1\Terminal\TerminalQds\Entity\Repository\TerminalSettingRepository;
use Yii;
use Exception;

class TerminalSettingService
{
    /**
     * @var TerminalSettingRepository
     */
    protected $terminalRepository;
    /**
     * @var TerminalRepository
     */
    protected $terminalRepo;

    /**
     * @param TerminalSettingRepository $terminalRepository
     * @param TerminalRepository $terminalRepo
     */
    public function __construct(TerminalSettingRepository $terminalRepository, TerminalRepository $terminalRepo)
    {
        $this->terminalRepository = $terminalRepository;
        $this->terminalRepo = $terminalRepo;
    }

    /**
     * @param $terminalID
     * @return QdsSettingDto
     */
    public function getTerminalSettings($terminalID): QdsSettingDto
    {
        $branchID = $this->terminalRepo->findCurrentBranch();
        $terminal = $this->terminalRepo->checkActiveTerminal($branchID, $terminalID);
        if(!$terminal){
            return new QdsSettingDto();
        }

        $terminalModel = $this->terminalRepository->findByTerminalID($terminalID);
        $qdsMapping = $this->qdsSetting();

        $terminalData = [];
        foreach ($terminalModel as $data) {
            $mappedKey = $qdsMapping[$data->key] ?? $data->key;
            $terminalData[$mappedKey] = in_array($mappedKey, ['additionalInfo', 'showTableInfo', 'activeStation'])
                ? (int) $data->value
                : $data->value;
        }

        $terminalData['terminalID'] = $terminalID;
        return new QdsSettingDto($terminalData);
    }

    /**
     * @param QdsSettingDto $dto
     * @return bool
     */
    public function saveQdsTerminalSettings(QdsSettingDto $dto): bool
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $qdsSettings = $dto->qdsValue();

            foreach ($qdsSettings as $key => $value) {

                if ($key == Constants::TERMINAL_QDS_STATIONS) {
                    $value = $this->terminalRepository->findStations($value);
                }

                if ($key === Constants::TERMINAL_QDS_SALES_MODE) {
                    $value = $this->terminalRepository->findVisitPurpose($value);
                }
                if (!$this->runSaveQdsTerminalSetting($key, $value, $dto['terminalID'])) {
                    throw new Exception("Failed to save setting: $key");
                }
            }
            $this->saveLog($dto['terminalID'], $dto->toArray());
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex);
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * @param $key
     * @param $value
     * @param $terminalID
     * @return bool
     */
    protected function runSaveQdsTerminalSetting($key, $value, $terminalID): bool
    {
        $settingModel = $this->terminalRepository->findOrCreateTerminalSetting($terminalID, $key, $value);
        if(!$settingModel){
            return false;
        }
        return true;
    }

    /**
     * @param $terminalID
     * @param $data
     * @return void
     */
    protected function saveLog($terminalID, $data): void
    {
        Logging::save($terminalID, Logging::QDS_TERMINAL_SETTING_CHANGE, $data);
    }

    /**
     * @return string[]
     */
    protected function qdsSetting(): array
    {
        return [
            Constants::TERMINAL_QDS_SALES_MODE => 'visitPurposeIds',
            Constants::TERMINAL_QDS_SHOW_ADDITIONAL_INFO => 'additionalInfo',
            Constants::TERMINAL_QDS_SHOW_TABLE_NUMBER => 'showTableInfo',
            Constants::TERMINAL_QDS_STATIONS => 'stationID',
            Constants::TERMINAL_QDS_ACTIVE_STATION => 'activeStation',
            Constants::TERMINAL_QDS_LANGUAGE => 'selectedLanguage'
        ];
    }

}
