<?php

namespace app\models;
use yii\db\ActiveRecord;

class EsoWebSocketQueue extends ActiveRecord {
    
    public static function tableName() {
        return 'tr_esowebsocketqueue';
    }

    public function rules() {
        return [
            [['webSocketID'], 'required']
        ];
    }
}
