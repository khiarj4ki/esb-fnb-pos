<?php

namespace app\modules\v1\controllers;

use app\modules\v1\Terminal\TerminalQds\Dto\QdsSettingDto;
use app\modules\v1\Terminal\TerminalQds\Service\TerminalService;
use app\modules\v1\Terminal\TerminalQds\Service\TerminalSettingService;
use Yii;
use yii\web\HttpException;

class QdsTerminalController extends BaseController
{
    private $terminalService;
    private $terminalSettingService;

    public function __construct($id, $module, TerminalService $terminalService, TerminalSettingService $terminalSettingService, $config = [])
    {
        $this->terminalService = $terminalService;
        $this->terminalSettingService = $terminalSettingService;
        parent::__construct($id, $module, $config);
    }

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
                ['save-terminal', 'get-qds-terminal-setting', 'save-qds-terminal-setting'
        ]);
        return $behaviors;
    }

    public function actionSaveTerminal()
    {
        return $this->terminalService->saveTerminal();
    }

    public function actionGetQdsTerminalSetting()
    {
        $terminalID = Yii::$app->request->post('terminalID');
        return $this->terminalSettingService->getTerminalSettings($terminalID);
    }

    public function actionSaveQdsTerminalSetting()
    {

        $postData = Yii::$app->request->post();

        if(!$postData){
            throw new HttpException(500, "No Valid Request");
        }

        $qdsSettingDto = new QdsSettingDto($postData);

        return $this->terminalSettingService->saveQdsTerminalSettings($qdsSettingDto);
    }
}