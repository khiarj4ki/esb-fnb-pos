<?php

namespace app\components;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class GStickerPrinter {
    const START_POSITION = 15;
    const ROW_NEW_LINE = 30;

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

    public function write($text, $font) {
        $this->printer->getPrintConnector()->write("TEXT 20,$this->currentY,\"$font\",0,1,1,\"$text\"\n");
        $addingLine = self::ROW_NEW_LINE;
        $this->currentY = $this->currentY + $addingLine;     
    }

    public function closeWrite() {
        $this->printer->getPrintConnector()->write("PRINT 1\n");
    }

    public function clear() {
        $this->printer->getPrintConnector()->write("\nCLS\n");
        $this->currentY = self::START_POSITION;
    }

    public function close() {
        $this->printer->close();
    }

}