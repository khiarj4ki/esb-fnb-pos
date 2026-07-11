<?php
namespace app\modules\v1\Terminal\TerminalQds\Entity\Repository;

use app\models\Station;
use app\models\TerminalSetting;
use app\models\VisitPurpose;
use app\modules\v1\Terminal\TerminalQds\Exception\TerminalException;
use app\modules\v1\Terminal\TerminalQds\Exception\TerminalExceptionInterface;
use Exception;

class TerminalSettingRepository implements TerminalSettingRepositoryInterface
{

    /**
     * @inheritdoc
     */
    public function findByTerminalID($terminalID): array
    {
        return TerminalSetting::find()->where(['terminalID' => $terminalID])->all();
    }

    /**
     * @inheritdoc
     */
    public function findOrCreateTerminalSetting($terminalID, $key, $value)
    {
        $settingModel = TerminalSetting::findTerminalSetting($terminalID, $key);
        if ($settingModel) {
            $settingModel->value = ($value);
            $this->saveSetting($settingModel);
        } else {
            $settingModel = new TerminalSetting();
            $settingModel->terminalID = $terminalID;
            $settingModel->key = $key;
            $settingModel->value = ($value);
            $this->saveSetting($settingModel);
        }
        return $settingModel;
    }

    /**
     * @throws Exception
     */
    private function saveSetting($settingModel){
        if (!$settingModel->save()) {
            TerminalException::error(TerminalExceptionInterface::FAILED_UPDATE_TERMINAL_SETTING);
        }
    }

    /**
     * @inheritdoc
     */
    public function findStations($value): ?string
    {
            $stationArray = explode(',', $value);
            $stationList = Station::find()
                    ->select('stationID')
                    ->where(['stationID' => $stationArray])
                    ->andWhere(['flagActive' => 1])
                    ->column();
            
            return $stationList ? implode(',',$stationList) : null;
    
    }

    /**
     * @inheritdoc
     */
    public function findVisitPurpose($value): ?string
    {
            $vpArray = explode(',', $value);
            $vpList = VisitPurpose::find()
                    ->select('visitPurposeID')
                    ->where(['IN', 'visitPurposeID', $vpArray])
                    ->andWhere(['flagActive' => 1])
                    ->column();

            return $vpList ? implode(',',$vpList) : null;
    }
}
