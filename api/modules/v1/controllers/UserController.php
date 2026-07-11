<?php
namespace app\modules\v1\controllers;

use app\models\forms\ChangePassword;
use app\models\forms\Logging;
use app\models\PosUser;
use app\models\Setting;
use app\models\MsNotificationHead;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class UserController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'check-session', 'login'
        ]);
        return $behaviors;
    }

    public function actionCheckSession() {

        //  @notes: Check Pos Password and Salt untuk user suspend
        $username = strval($this->request->post('username'));
        $password = strval($this->request->post('password'));
        
        // @check: check validate for logic autosync 
        self::validatePosUser($username);

        $user = $this->validateUsernamePassword($username, $password);

        if (!$user) {
            throw new HttpException(404, Yii::t('app', $this->getErrorMessage()));
        }

        return $this->returnUser($user);
    }

    public function actionLogin() {
        $user = $this->validateUsernamePassword($this->request->post('username'),
            $this->request->post('password'));
        

        if (!$user) {
            throw new HttpException(404, Yii::t('app', $this->getErrorMessage()));
        }
        $user->login();
        
        if ($user) {
            $user->posNotification = MsNotificationHead::fetchLatestPosNotification();
        }

        return $this->returnUser($user);
    }

    public function actionGetUser() {
        $user = PosUser::findIdentityByAccessToken(Yii::$app->user->identity->posAuthKey);
        if ($user) {
            return $this->returnUser($user);
        }
        throw new HttpException(404, Yii::t('app', 'User not found'));
    }

    public function actionLogout() {
        $user = PosUser::findIdentityByAccessToken(Yii::$app->user->identity->posAuthKey);
        if ($user) {
            $user->scenario = PosUser::SCENARIO_AUTH;
            $user->posAuthKey = null;
            if($user->save()){
                Logging::save($user->username,
                       Logging::SIGNOUT,
                       $user->getAttributes());   
            }
        }
    }

    public function actionChangePassword() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $changeModel = new ChangePassword([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$changeModel->save()) {
                throw new Exception(json_encode($changeModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data' . $ex->getMessage()));
        }
    }

    private function validateUsernamePassword($username, $password) {
        // @notes: ubah password & username menjadi integer, case swipe card ada 0 didepan
        $user = PosUser::findIdentity($username);
        if(!$user || !$user->validatePassword($password)) {
            return null;
        }

        return $user;
    }

    private function returnUser($user, $withAccess = true) {
        return [
            'username' => $user->username,
            'userID' => $user->posUserID,
            'fullName' => $user->fullName,
            'branchID' => $user->branchID,
            'branchName' => $user->branch->branchName,
            'token' => $user->posAuthKey,
            'loggedIn' => !empty($user->posAuthKey),
            'userAccess' => $withAccess ? $user->getUserAccess() : [],
            'posNotification' => isset($user->posNotification) ? $user->posNotification : null
        ];
    }

    private function getErrorMessage() {
        $loginType = Setting::getValue1('POS', 'Login Type');

        $message = '';
        if ($loginType == 'Username & Password') {
            $message = 'Invalid User ID or PIN.';
        } else {
            $message = 'Invalid PIN.';
        }

        return $message . ' Please try again.';
    }
    
    private static function validatePosUser($username){

        $posUser = PosUser::findIdentity($username);
        $modelPosUser = PosUser::find()
                ->where(['<>', 'posPassword', ''])
                ->andWhere(['<>', 'posSalt', '']) 
                ->all();

        if(!$modelPosUser){
            throw new HttpException(404, Yii::t('app', 'There is no user active.'));
        }
        if(!$posUser){
            throw new HttpException(404, Yii::t('app', 'This user is not found.'));
        }
        if($posUser && $posUser->posPassword == '' && $posUser->posSalt == ''){
            throw new HttpException(404, Yii::t('app', 'This user is suspended.'));
        }
    }

}
