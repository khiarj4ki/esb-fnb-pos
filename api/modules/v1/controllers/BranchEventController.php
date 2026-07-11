<?php
namespace app\modules\v1\controllers;

use app\models\BranchEvent;
use app\models\forms\Logging;
use Yii;
use yii\db\Expression;
use yii\web\HttpException;

class BranchEventController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $branchEventModel = BranchEvent::find();
        if ($this->request->post('eventDate')) {
            $branchEventModel->andWhere(['DATE(eventDate)' => new Expression('DATE(\'' . $this->request->post('eventDate') . '\')')]);
        }
        
        $branchEventList = [];
        foreach ($branchEventModel->all() as $branchEvent) {
            $description = '';
            $branchEventArr = $branchEvent->toArray();
            $array = is_array(json_decode($branchEventArr['eventDescription'], true)) ? true : false;
            if ($array) {
                $descriptions = json_decode($branchEventArr['eventDescription'], true);
                foreach ($descriptions as $key => $descriptionDetail) {
                    if(is_array($descriptionDetail)){
                        $description .= $key . ":  \n\n" . BranchEvent::getStringArray($descriptionDetail) . "\n";
                    } else {
                        $description .= $key . ': ' . $descriptionDetail . "\n";
                    }
                }
            } else {
                $description .= $branchEventArr['eventDescription'];
            }
            $branchEventArr['eventDescription'] = $description;
            $branchEventList[] = $branchEventArr;
        }
        return $branchEventList;
    }
    
    public function actionSave() {
        if (
            !$this->request->post('refNum') ||
            !$this->request->post('eventSubject') ||
            !$this->request->post('eventDescription')
        ) {
            throw new HttpException(400);
        }
        
        try {
            $data = $this->request->post();
            Logging::save(
                isset($data['refNum']) ? $data['refNum'] : null,
                $data['eventSubject'],
                $data['eventDescription']
            );
            return true;
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }
}
