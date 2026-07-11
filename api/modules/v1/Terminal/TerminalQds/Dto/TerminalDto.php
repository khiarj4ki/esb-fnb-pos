<?php

namespace app\modules\v1\Terminal\TerminalQds\DTO;

class TerminalDto
{
    public $terminalID;
    public $caption;
    public $deviceType;
    public $activatedDate;

    public function __construct($terminalID, $caption, $deviceType, $activatedDate)
    {
        $this->terminalID = $terminalID;
        $this->caption = $caption;
        $this->deviceType = $deviceType;
        $this->activatedDate = $activatedDate;
    }
}