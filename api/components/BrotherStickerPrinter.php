<?php

namespace app\components;

use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Yii;

class BrotherStickerPrinter {
    const START_POSITION = 0;
    const ROW_NEW_LINE = 25;

    private $host;
    private $port;
    private $charLength;
    private $printer;
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
        $this->printer->getPrintConnector()->write("\x1B\x24\x20\x00\x1B\x6B\x0B$text\n");
    }

    public function closeWrite() {
        $this->printer->getPrintConnector()->write("\x0C");
    }

    public function clear() {
        $this->printer->getPrintConnector()->write("\x1B\x69\x61\x00\x1B\x40");
    }

    public function close() {
        $this->printer->close();
    }

}