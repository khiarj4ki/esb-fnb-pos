<?php

namespace app\modules\v1\Terminal\TerminalQds\Service;

use app\models\forms\Logging;
use app\modules\v1\Terminal\TerminalQds\DTO\TerminalDto;
use app\modules\v1\Terminal\TerminalQds\Entity\Repository\TerminalRepository;
use app\modules\v1\Terminal\TerminalQds\Exception\TerminalException;
use app\modules\v1\Terminal\TerminalQds\Exception\TerminalExceptionInterface;
use Exception;
use Yii;

class TerminalService
{
    const STATUS_USED = 47;

    /**
     * @var TerminalRepository
     */
    protected $terminalRepository;

    /**
     * @param TerminalRepository $terminalRepository
     */
    public function __construct(TerminalRepository $terminalRepository)
    {
        $this->terminalRepository = $terminalRepository;
    }

    public function saveTerminal()
    {
        try {
            $branchID = $this->terminalRepository->findCurrentBranch();
            $terminal = $this->terminalRepository->findActiveTerminal($branchID);
            $beforeValue = $terminal;
            if (!$terminal) {
                TerminalException::error(TerminalExceptionInterface::NO_VALID_TERMINAL);
            }

            $dto = new TerminalDto($terminal->terminalID, "LOUNGE " . $this->terminalRepository->countDeviceLounge($branchID), 'LOUNGE', date('Y-m-d H:i:s'));

            $terminal->statusID = self::STATUS_USED;
            $terminal->caption = $dto->caption;
            $terminal->deviceType = $dto->deviceType;
            $terminal->activatedDate = $dto->activatedDate;

            $this->terminalRepository->saveTerminal($terminal);
            $this->saveLog($beforeValue, $terminal);
            return $dto->terminalID;
        } catch (Exception $e) {
            Yii::warning($e->getMessage());
        }
    }

    /**
     * @param $terminalModel
     * @param $afterValue
     * @return void
     */
    protected function saveLog($terminalModel, $afterValue): void
    {
        Logging::save($terminalModel->terminalCode, Logging::SAVE_TERMINAL, $afterValue);
    }
}
