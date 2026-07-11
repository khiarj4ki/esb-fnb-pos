<?php

namespace app\components;

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;

class ExtPrinter extends Printer {

    // FOR STAR MPOP
    public function bitImageMpop(EscposImage $img, $size = Printer::IMG_DEFAULT)
    {
        self::validateInteger($size, 0, 3, __FUNCTION__);
        $rasterData = $img -> toRasterFormat();
        $header = Printer::dataHeader(array($img -> getWidthBytes(), $img -> getHeight()), true);
        $this -> connector -> write("\x1B\x1D\x53" . chr(1) . $header . chr(0));
        $this -> connector -> write($rasterData);
    }

}