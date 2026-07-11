<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\components;

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Yii;
use yii\web\NotFoundHttpException;
use function mb_convert_encoding;

/**
 * Description of LocalPrintingConnector
 *
 * @author dwi
 */
class AndroidPrintConnector extends FilePrintConnector {
    const BLUETOOTH = 'bluetooth';
    const LAN = 'LAN';
    const SUNMI_EXTERNAL = 'sunmi_external';
    const WINTEC_EXTERNAL = 'wintec_external';
    const SUNMI_T2S = 'sunmi_t2s';
    const SNBC_BTP_S80 = 'snbc_btp_s80';
    const USB = 'USB';

    private $tmpFile;
    private $printerName;
    private $connectionType;
    private $port;
    private $flagAutocut;
    private $flagOpenCashdrawer;

    public function __construct($printerName, $connectionType, $flagAutocut, $port = null, $flagOpenCashdrawer = 0) {
        $this->printerName = $printerName;
        $this->connectionType = $connectionType;
        $this->port = $port;
        $this->flagAutocut = $flagAutocut;
        $this->flagOpenCashdrawer = $flagOpenCashdrawer;

        $tmpDir = Yii::$app->runtimePath . '/tmp';

        if (!is_dir($tmpDir) && (!@mkdir($tmpDir) && !is_dir($tmpDir))) {
            throw new NotFoundHttpException('temp directory does not exist');
        }

        $this->tmpFile = tempnam($tmpDir, "print-");
        parent::__construct($this->tmpFile);
    }

    public function finalize() {
        parent::finalize();
        $data = file_get_contents($this->tmpFile);
        unlink($this->tmpFile);

        $session = Yii::$app->session;
        if (isset($session["print"])) {
            $printData = $session["print"];
        }
        $printData[] = [
            "printerId" => $this->printerName,
            "connectionType" => $this->connectionType,
            "port" => $this->port,
            "data" => urlencode(mb_convert_encoding('', 'UTF-8', 'UTF-8')), 
            "encodedData" => bin2hex($data),
            "dataType" => "hexbinary",
            "fullByte" => (bool) $this->flagAutocut,
            "openCashdrawer" => (bool) $this->flagOpenCashdrawer,
        ];
        
        $session->set("print", $printData);
    }

    public static function getData() {
        $session = Yii::$app->session;
        $data = "";
        if (isset($session['print'])) {
            $data = $session['print'];
            unset($session['print']);
            return is_null($data) ? [] : $data;
        }

        return [];
    }

}
