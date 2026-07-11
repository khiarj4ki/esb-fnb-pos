<?php
namespace app\modules\v1\Terminal\TerminalQds\Entity\Repository;

use app\models\Terminal;

interface TerminalRepositoryInterface {
    /**
     * @param $branchID
     * @return mixed
     */
    public function findActiveTerminal($branchID);

    /**
     * @param $branchID
     * @param $terminalID
     * @return mixed
     */
    public function checkActiveTerminal($branchID, $terminalID);

    /**
     * @param Terminal $terminal
     * @return mixed
     */
    public function saveTerminal(Terminal $terminal);

    /**
     * @return mixed
     */
    public function findCurrentBranch();

    /**
     * @param $branchID
     * @return mixed
     */
    public function countDeviceLounge($branchID);
}
