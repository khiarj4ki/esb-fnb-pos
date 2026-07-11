<?php

use app\models\PrinterConnection;
use yii\db\Migration;

/**
 * Class m210519_033414_insert_lk_printerconnection_sunmi
 */
class m210519_033414_insert_lk_printerconnection_sunmi extends Migration
{
    public function up() {
        if (!PrinterConnection::find()
                        ->where([
                            'printerConnectionID' => 7,
                            'printerConnectionName' => 'Sunmi Kiosk Printer Connection'
                        ])
                        ->exists()) {
            $this->insert(PrinterConnection::tableName(),
                    [
                        'printerConnectionID' => 7,
                        'printerConnectionName' => 'Sunmi Kiosk Printer Connection'
                    ]
            );
        }
    }

    public function down() {
        $this->delete(PrinterConnection::tableName(), [
            'printerConnectionID' => 7,
            'printerConnectionName' => 'Sunmi Kiosk Printer Connection'
        ]);
    }
}
