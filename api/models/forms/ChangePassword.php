<?php
namespace app\models\forms;

use app\models\PosUser;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property string $newPassword
 * @property string $newPasswordConf
 * 
 * PRIVATE
 * @property PosUser $userModel
 */
class ChangePassword extends Model {
    public $newPassword;
    public $newPasswordConf;
    public $userModel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['newPassword', 'newPasswordConf'], 'required'],
            [['newPassword', 'newPasswordConf'], 'string', 'max' => 50],
            [['newPasswordConf'], 'validateNewPassword']
        ];
    }

    public function validateNewPassword($attribute) {
        if ($this->newPassword != $this->newPasswordConf) {
            $this->addError($attribute, 'Invalid new PIN');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->userModel = PosUser::find()
                ->andWhere(['username' => Yii::$app->user->identity->username])
                ->one();
            if ($this->userModel) {
                $this->userModel->posSalt = Yii::$app->security->generateRandomString(45);
                $this->userModel->posPassword = md5(md5($this->newPassword) . $this->userModel->posSalt);
                if (!$this->userModel->save()) {
                    throw new Exception('Failed to save new password');
                }

                Logging::save('-', Logging::CHANGE_PASSWORD,
                    $this->getAttributes());
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollback();
            $this->addError('newPassword', $ex->getMessage());
            return false;
        }
    }

}
