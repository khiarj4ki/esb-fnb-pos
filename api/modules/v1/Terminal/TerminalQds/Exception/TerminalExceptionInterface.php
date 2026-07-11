<?php

namespace app\modules\v1\Terminal\TerminalQds\Exception;

interface TerminalExceptionInterface
{
    const ERROR_REQUEST_VALIDATION = 10000400;
    const INTERNAL_ERROR = 10000999;
    const SETTING_BRANCH_ID_NOTFOUND = 10000001;
    const ERROR_INTERNAL_REQUEST_DTO = 10000002;
    const NO_VALID_TERMINAL = 10000003;
    const NO_VALID_TERMINAL_SETTING = 10000006;
    const FAILED_SAVE_TERMINAL =10000004;
    const FAILED_UPDATE_TERMINAL_SETTING = 10000005;
}