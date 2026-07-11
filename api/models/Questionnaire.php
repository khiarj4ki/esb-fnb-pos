<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_questionnaire".
 *
 * @property int $id
 * @property int $questionType
 * @property string $questionText
 * @property int $acceptOtherAnswer
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class Questionnaire extends ActiveRecord {
    public static function tableName() {
        return 'ms_questionnaire';
    }

    public function rules() {
        return [
            [['ID', 'questionType', 'questionText', 'acceptOtherAnswer', 'flagActive'], 'required'],
            [['ID', 'questionType', 'acceptOtherAnswer', 'nextQuestionID', 'flagActive'], 'integer'],
            [['createdDate', 'editedDate'], 'safe'],
            [['type', 'id'], 'string', 'max' => 50],
            [['questionText', 'createdBy', 'editedBy'], 'string', 'max' => 100],
        ];
    }

    public function attributeLabels() {
        return [
            'ID' => 'Question ID',
            'questionType' => 'Question Type',
            'questionText' => 'Question Text',
            'nextQuestionID' => 'Next Question',
            'acceptOtherAnswer' => 'Accept Other Answer',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public static function findActiveAsArray() {
        $questionModel = Questionnaire::find()
            ->where(['flagActive' => 1])
            ->orderBy(Questionnaire::tableName() . '.order')
            ->all();
        $questionArray = [];
        $i = 0;
        foreach($questionModel as $questionnaire) {
            $questionOptionArray = [];
            $j = 0;
            $questionArray[$i]['ID'] = $questionnaire->ID;
            $questionArray[$i]['questionType'] = $questionnaire->questionType;
            $questionArray[$i]['questionText'] = $questionnaire->questionText;
            $questionArray[$i]['acceptOtherAnswer'] = $questionnaire->acceptOtherAnswer;
            $questionArray[$i]['nextQuestionID'] = $questionnaire->nextQuestionID;
            $questionOptionModel = QuestionOption::find()
                ->where(['questionID' => $questionnaire->ID])
                ->all();
            foreach ($questionOptionModel as $questionOption) {
                $questionOptionArray[$j]['ID'] = $questionOption->ID;
                $questionOptionArray[$j]['questionID'] = $questionOption->questionID;
                $questionOptionArray[$j]['answerText'] = $questionOption->answerText;
                $questionOptionArray[$j]['nextQuestionID'] = $questionOption->nextQuestionID;
                $j++;
            }
            $questionArray[$i]['questionAnswer'] = $questionOptionArray;
            $i++;
        }
        return $questionArray;
    }

}
