<?php

namespace app\modules\v1\controllers;

use app\components\AppHelper;
use app\models\forms\MemberDepositWithdrawalOnline;
use app\models\Member;
use app\models\MemberDeposit;
use Exception;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\HttpException;

class MemberController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            []
        );
        return $behaviors;
    }

    public function actionIndex()
    {
        ini_set('memory_limit', '-1');
        return Member::findActive()->all();
    }

    public function actionView()
    {
        if (!$this->request->post('memberCode')) {
            throw new HttpException(400);
        }

        $memberModel = $this->findMember($this->request->post('memberCode'));
        $extraFields = [
            'deposit' => (float) MemberDeposit::getOutstandingDeposit($memberModel->memberCode)
        ];

        return array_merge(
            ArrayHelper::toArray($memberModel, Member::class),
            $extraFields
        );
    }

    public function actionViewOnline(){
        $MemberDepositWithdrawalOnline = new MemberDepositWithdrawalOnline([
            'attributes' => $this->request->post()
        ]);
        return $MemberDepositWithdrawalOnline->getMember();
    }

    public function actionCreate()
    {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $memberModel = new Member([
            'attributes' => $this->request->post()
        ]);
        $memberModel->memberTypeID = 1;
        $memberModel->memberCode = AppHelper::createNewMemberCode();
        $memberModel->memberAddress = AppHelper::checkSpecialChar($memberModel->memberAddress);
        
        try {
            if (!$memberModel->save()) {
                throw new Exception(json_encode($memberModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionUpdate()
    {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $memberModel = $this->findMember($this->request->post('memberCode'));
        try {
            $memberModel->attributes = $this->request->post();
            $memberModel->memberAddress = AppHelper::checkSpecialChar($memberModel->memberAddress);
            if (!$memberModel->save()) {
                throw new Exception(json_encode($memberModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to update data'));
        }
    }

    public function findMember($memberCode)
    {
        $memberModel = Member::findActive()
            ->andWhere(['memberCode' => $memberCode])
            ->one();

        if (!$memberModel) {
            throw new HttpException(404, Yii::t('app', 'Member not found'));
        }

        return $memberModel;
    }
}
