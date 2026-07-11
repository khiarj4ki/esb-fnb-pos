<?php

namespace app\models;

use Exception;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_questionanswer".
 *
 * @property int $id
 * @property int $questionID
 * @property string $answerText
 * @property int $nextQuestionID
 * @property int $order
 */
class QuestionAnswer extends ActiveRecord {
    public $questionAnswers;
    
    public static function tableName() {
        return 'tr_questionanswer';
    }
    
    public function rules() {
        return [
            [['questionID', 'salesNum', 'answerText', 'salesNum'], 'required'],
            [['questionID'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
            [['answerText'], 'string', 'max' => 500],
            [['syncDate', 'questionAnswers'], 'safe']
        ];
    }
    
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'salesNum' => 'Sales Num',
            'questionID' => 'Question ID',
            'answerText' => 'Answer Text',
        ];
    }

    public function saveModel() {
        try {
            $transaction = Yii::$app->db->beginTransaction();
            if (isset($this->questionAnswers) && count($this->questionAnswers) > 0) {
                foreach ($this->questionAnswers as $questionAnswer) {
                    if ((isset($questionAnswer['ID']) && $questionAnswer['ID'] > 0) && (isset($questionAnswer['answerText']) && $questionAnswer['answerText'] !== null)) {
                        $this->salesNum = $questionAnswer['salesNum'];
                        $this->questionID = $questionAnswer['questionID'];
                        $this->answerText = $questionAnswer['answerText'];
                        if (!$this->save()) {
                            throw new Exception("Failed to save question answer");
                        }
                    }
                }
            }
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

    public static function syncUpdate($syncDate, $ID) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            QuestionAnswer::updateAll(['syncDate' => $syncDate],
                ['ID' => $ID]
            );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }
}
