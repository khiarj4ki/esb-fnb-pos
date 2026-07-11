<?php

namespace app\components;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Yii;

class BixolonStickerPrinter {
    const START_POSITION = 20;
    const ROW_NEW_LINE = 30;
    const START_POSITION_X = 40;

    private $host;
    private $port;
    private $charLength;
    private $printer;
    private $currentY = self::START_POSITION;
    private $startPositionX = self::START_POSITION_X;
    private $stationModel;

    function __construct($host, $connectionType, $stationModel, $port = 9100, $charLength = 30) {
        $this->host = $host;
        $this->port = $port;
        $this->charLength = $charLength;
        $this->setConnection($connectionType);
        $this->stationModel = $stationModel;
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
        $this->printer->getPrintConnector()->write("T$this->startPositionX, $this->currentY,1,1,1,0,0,N,N,'$text'\n");
        $addingLine = self::ROW_NEW_LINE;
        $this->currentY = $this->currentY + $addingLine;
    }

    public function closeWrite() {
        $this->printer->getPrintConnector()->write("P1\n");
    }

    public function clear() {
        $this->printer->getPrintConnector()->write("\nCLS\n");
        $this->printer->getPrintConnector()->write("N\n");
        $this->currentY = self::START_POSITION;
    }

    public function close() {
        $this->printer->close();
    }

}