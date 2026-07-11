<?php

namespace app\components;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Yii;

class SatoStickerPrinter {
    const START_POSITION = 200;
    const ROW_NEW_LINE = 30;

    private $host;
    private $port;
    private $charLength;
    private $printer;
    private $currentY = self::START_POSITION;
    private $stringArr;
    private $stationModel;


    function __construct($host, $connectionType, $stationModel, $port = 9100, $charLength = 30) {
        $this->host = $host;
        $this->port = $port;
        $this->charLength = $charLength;
        $this->stationModel = $stationModel;

        $this->setConnection($connectionType);
    }

    private function setConnection($connectionType) {
        if ($connectionType == 2) {
            $connector = new WindowsPrintConnector($this->host);
        } else if ($connectionType == 3) {
            $connector = new AndroidPrintConnector($this->host, AndroidPrintConnector::BLUETOOTH, $this->stationModel->flagAutocut);
        } else if ($connectionType == 5) {
            $connector = new CupsPrintConnector($this->host);
        }

        $profile = CapabilityProfile::load("simple");
        $this->printer = new Printer($connector, $profile);
    }

    public function write($text) {
        $this->stringArr .= "\x1bV$this->currentY\x1bH340\x1bP2\x1bL0202\x1bRDB00,P10,P10,$text\n";
        $addingLine = self::ROW_NEW_LINE;
        $this->currentY = $this->currentY - $addingLine;     
    }

    public function closeWrite() {
        $this->stringArr .= "\x1bQ1\n";
        $this->stringArr .= "\x1bZ\n";
        $this->printer->getPrintConnector()->write($this->stringArr);
    }

    public function clear() {
        $this->stringArr = "\x1bA\x1b%2\n";
        $this->currentY = self::START_POSITION;
    }

    public function close() {
        $this->printer->close();
    }

}