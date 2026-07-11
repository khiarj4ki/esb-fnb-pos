<?php
namespace app\models;

use app\models\forms\Logging;
use Underscore\Types\Arrays;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "ms_posuser".
 *
 * @property string $username
 * @property string $fullName
 * @property string $password
 * @property string $salt
 * @property int $posUserRoleID
 * @property int $branchID
 * @property string $posAuthKey
 * @property string $posUserID
 * @property string $posPassword
 * @property string $posSalt
 * @property string $syncDate
 * 
 * @property Branch $branch
 * @property PosUserRole $userRole
 */
class PosUser extends ActiveRecord implements IdentityInterface {
    public $posNotification;
    const SCENARIO_AUTH = 'scenario login or logout';

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_posuser';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['username', 'fullName', 'salt', 'posUserRoleID', 'branchID', 'posUserID', 'posPassword', 'posSalt'], 'required'],
            [['posUserRoleID', 'branchID'], 'integer'],
            [['syncDate', 'posNotification'], 'safe'],
            [['username', 'posUserID', 'posPassword'], 'string', 'max' => 100],
            [['fullName'], 'string', 'max' => 200],
            [['password'], 'string', 'max' => 255],
            [['posUserID', 'posPassword'], 'string', 'max' => 50],
            [['salt', 'posSalt'], 'string', 'max' => 45],
            [['posAuthKey'], 'string', 'max' => 50],
            [['username'], 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($posUserID) {
        $branchID = Setting::getCurrentBranch();

        return PosUser::find()
                ->joinWith('userRole')
                ->andWhere(['posUserID' => $posUserID])
                ->andWhere(['branchID' => $branchID])
                ->andWhere([PosUserRole::tableName() . '.flagActive' => 1])
                ->one();
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null) {
        return PosUser::findOne(['posAuthKey' => $token]);
    }

    /**
     * {@inheritdoc}
     */
    public function getId() {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey() {
        return $this->posAuthKey;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey) {
        return $this->posAuthKey === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password) {
        return $this->posPassword === md5(md5($password) . $this->posSalt);
    }

    public function login() {
        $security = Yii::$app->security;
        $authKey = $security->generateRandomString(50);

        // Prevent duplicate token
        while (PosUser::findIdentityByAccessToken($authKey)) {
            $authKey = $security->generateRandomString(50);
        }

        $this->scenario = self::SCENARIO_AUTH;
        $this->posAuthKey = $authKey;
        if($this->save()){
            Logging::save($this->username,
                   Logging::SIGNIN,
                   $this->getAttributes());   
        }
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_AUTH] = [
            'username',
            'fullName',
            'password',
            'salt',
            'posUserRoleID',
            'branchID',
            'posAuthKey',
            'posUserID',
            'posPassword',
            'posSalt',
            'syncDate'
        ];

        return $scenarios;
    }

    public function getBranch() {
        return $this->hasOne(Branch::class, ['branchID' => 'branchID']);
    }

    public function getUserRole() {
        return $this->hasOne(PosUserRole::class,
                ['posUserRoleID' => 'posUserRoleID']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->scenario != self::SCENARIO_AUTH) {
            $this->syncDate = null;
        }

        return true;
    }

    public function getUserAccess() {
        $accessModelArray = [];

        $accessModel = PosUserAccess::find()
            ->joinWith('filterAccess')
            ->andWhere(['posUserRoleID' => $this->posUserRoleID])
            ->orderBy('orderID')
            ->all();
        foreach ($accessModel as $access) {
            $accessModelArray[] = $access->toArray();
        }

        $posAccessIDQuery = PosFilterAccess::find()
            ->select('posAccessID')
            ->innerJoin(PosUserAccess::tableName() . ' b',
                PosFilterAccess::tableName() . '.filterAccessID = b.filterAccessID AND ' .
                'posUserRoleID = ' . $this->posUserRoleID)
            ->andWhere(['hasAccess' => 1]);
        $accessControlModel = PosAccessControl::find()
            ->andWhere(['IN', 'posAccessID', $posAccessIDQuery])
            ->orderBy('orderID')
            ->all();
        $userAccesses = [];
        foreach ($accessControlModel as $accessControl) {
            $userAccess = $accessControl->toArray();
            $accesses = Arrays::filter($accessModelArray,
                    function ($access) use ($accessControl) {
                    return $access['posAccessID'] == $accessControl->posAccessID;
                });
            $userAccess['access'] = array_merge([], $accesses);
            $userAccesses[] = $userAccess;
        }

        return $userAccesses;
    }

    public static function changePassword($username, $newPassword, $newPasswordConf) {
        if ($newPassword != $newPasswordConf) {
            return [
                'status' => false,
                'message' => 'Invalid new PIN'
            ];
        }

        $userModel = PosUser::find()
            ->andWhere(['username' => $username])
            ->one();
        if ($userModel) {
            $security = Yii::$app->security;
            $userModel->posSalt = $security->generateRandomString(45);
            $userModel->posPassword = md5(md5($newPassword) . $userModel->posSalt);
            $userModel->syncDate = null;
            $userModel->save();
        }

        return [
            'status' => true
        ];
    }

    public static function syncUpdate($username, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            PosUser::updateAll([
                'syncDate' => $syncDate
                ],
                ['AND', ['branchID' => $branchID], ['username' => $username]
            ]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }

}
