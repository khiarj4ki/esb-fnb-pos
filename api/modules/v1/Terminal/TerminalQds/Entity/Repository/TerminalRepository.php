<?php
namespace app\modules\v1\Terminal\TerminalQds\Entity\Repository;

use app\models\Setting;
use app\models\Terminal;
use app\modules\v1\Terminal\TerminalQds\Exception\TerminalException;
use app\modules\v1\Terminal\TerminalQds\Exception\TerminalExceptionInterface;
use Exception;

class TerminalRepository implements TerminalRepositoryInterface
{
    public function findActiveTerminal($branchID)
    {
        return Terminal::find()
            ->where(['branchID' => $branchID, 'statusID' => 48])
            ->andWhere(['OR', ['deviceType' => null], ['deviceType' => ''], ['deviceType' => 'LOUNGE']])
            ->one();
    }

    public function checkActiveTerminal($branchID, $terminalID)
    {
        return Terminal::find()
            ->where(['branchID' => $branchID])
            ->andWhere(['terminalID' => $terminalID])
            ->one();
    }

    /**
     * @throws Exception
     */
    public function saveTerminal(Terminal $terminal): bool
    {
        if (!$terminal->save()) {
            TerminalException::error(TerminalExceptionInterface::FAILED_SAVE_TERMINAL);
        }
        return true;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function findCurrentBranch(): int
    {
        $setting = (int) Setting::getCurrentBranch();
        if (!$setting) {
            TerminalException::error(TerminalExceptionInterface::SETTING_BRANCH_ID_NOTFOUND);
        }

        return $setting;
    }

    /**
     * @param $branchID
     * @return bool|int|string|null
     */
    public function countDeviceLounge($branchID){
        return Terminal::find()
        ->where(['branchID' => $branchID, 'statusID' => 48])
        ->andWhere(['deviceType' => 'LOUNGE'])
        ->count() + 1;
    }
}
