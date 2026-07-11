<?php

namespace app\components;

use Exception;
use Yii;

class StickerPrinter {

    const MARGIN_LEFT = 30;
    const SPACE_BETWEEN_LINE = 30;
    const START_POSITION = 180;
    const EOL = "\r\n";

    private $host;
    private $port;
    private $charLength;
    public $errorNo;
    public $errorMessage;
    private $commands = [];
    private $currentY = self::START_POSITION;

    function __construct($host, $port = 9100, $charLength = 30) {
        $this->host = $host;
        $this->port = $port;
        $this->charLength = $charLength;
        $this->commands[] = "CLIP ON";
    }

    public function addLine($text, $fontSize = 10, $bold = false, $x = self::MARGIN_LEFT) {
        if ($this->currentY < 0) {
            throw new Exception("Maximum line exceed. Current Y : " . $this->currentY);
        }
        $substrText = substr($text, 0, $this->charLength);
        $printedText = str_replace('"', '\"', $substrText);

        if ($bold) {
            $font = "Univers Bold";
        } else {
            $font = "Univers";
        }

        $this->commands[] = 'FT "' . $font . '",' . $fontSize;
        $this->commands[] = 'PP ' . $x . ',' . $this->currentY;
        $this->commands[] = 'PT "' . $printedText . '"';
        $this->currentY = $this->currentY - (self::SPACE_BETWEEN_LINE * $fontSize / 10);

        if ($substrText != $text) {
            $substrText = str_replace($substrText, "", $text);
            $this->addLine($substrText, $fontSize, $bold, $x);
        }
    }

    public function addBlankLine() {
        $this->currentY -= 10;
    }

    public function sendToPrinter() {

        try {
            $fp = fsockopen($this->host, $this->port, $this->errorNo, $this->errorMessage, 30);
            Yii::error("fp:".$fp);
            if (!$fp) {
                Yii::error($this->errorMessage, "Sticker Printer Error");
                return false;
            } else {
                $commands = $this->getCommands();
                Yii::error("getCommands:". json_encode($commands));
                fwrite($fp, utf8_encode($commands));
                fclose($fp);
                $fp = false;

                return true;
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage(), "Sticker Printer Error");
            Yii::error(print_r($this->commands, true), "Sticker Printer Error");
            return false;
        }
    }

    private function getCommands() {
        $commands = $this->commands;
        $commands[] = "PF";
        $commands[] = "";
        Yii::error("commands:".json_encode($commands));
        return implode(self::EOL, $commands);
    }

}
