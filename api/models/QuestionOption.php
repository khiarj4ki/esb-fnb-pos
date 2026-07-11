<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_questionoption".
 *
 * @property int $id
 * @property int $questionID
 * @property string $answerText
 * @property int $nextQuestionID
 * @property int $order
 */
class QuestionOption extends ActiveRecord {
    
    public static function tableName() {
        return 'ms_questionoption';
    }
    
    public function rules() {
        return [
            [['ID', 'questionID', 'answerText'], 'required'],
            [['ID', 'questionID', 'nextQuestionID', 'order'], 'integer'],
            [['answerText'], 'string', 'max' => 100],
        ];
    }
    
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'questionID' => 'Question ID',
            'answerText' => 'Answer Text',
            'nextQuestionID' => 'Next Question ID',
            'order' => 'Order'
        ];
    }
}
