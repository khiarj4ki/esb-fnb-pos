<?php

namespace app\components;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Yii;

class ZebraStickerPrinter {
    const START_POSITION = 0;
    const ROW_NEW_LINE = 25;

    private $host;
    private $port;
    private $charLength;
    private $printer;
    private $currentY = self::START_POSITION;
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

    public function write($text, $fontSize = '2') {
        $textFormat = "A10,$this->currentY,0,$fontSize,1,1,N,\"$text\"\n";
        $this->printer->getPrintConnector()->write($textFormat);
        $addingLine = self::ROW_NEW_LINE;
        $this->currentY = $this->currentY + $addingLine;     
    }

    public function closeWrite() {
        $this->printer->getPrintConnector()->write("P1\n");
    }

    public function clear() {
        $this->printer->getPrintConnector()->write("CLS\n");
        $this->printer->getPrintConnector()->write("N\n");
        $this->currentY = self::START_POSITION;
    }

    public function close() {
        $this->printer->close();
    }

}