<?php
namespace app\modules\v1\Terminal\TerminalQds\Entity\Repository;

interface TerminalSettingRepositoryInterface {

    /**
     * @param $terminalID
     * @return mixed
     */
    public function findByTerminalID($terminalID);

    /**
     * @param $terminalID
     * @param $key
     * @param $value
     * @return mixed
     */
    public function findOrCreateTerminalSetting($terminalID, $key, $value);

    /**
     * @param $value
     * @return mixed
     */
    public function findStations($value);

    /**
     * @param $value
     * @return mixed
     */
    public function findVisitPurpose($value);
}
