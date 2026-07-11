<?php

namespace app\models;

use Exception;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_device".
 *
 * @property string $macAddress
 * @property string $terminalID
 */
class Device extends ActiveRecord {
    public static function tableName() {
        return 'ms_device';
    }

    public function rules() {
        return [
            [['macAddress', 'terminalID'], 'string', 'max' => 50],
        ];
    }
}
