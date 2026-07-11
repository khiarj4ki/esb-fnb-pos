<?php
use app\models\PrinterType;
use yii\db\Migration;

/**
 * Class m191023_110329_insert_printer_lk_printertype
 */
class m191023_110329_insert_printer_lk_printertype extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PrinterType::find()->where(['printerTypeID' => 6])->exists()) {
            $this->insert(PrinterType::tableName(),
                ['printerTypeID' => 6, 'printerTypeName' => 'Epson Sticker']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if (PrinterType::find()->where(['printerTypeID' => 6])->exists()) {
            $this->delete(PrinterType::tableName(), ['printerTypeID' => 6]);
        }
    }

}
