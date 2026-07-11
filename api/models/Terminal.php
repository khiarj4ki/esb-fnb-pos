<?php
namespace app\models;

use app\models\forms\Logging;
use app\services\http_helper\HttpHelperService;
use yii\db\ActiveRecord;
use Exception;
use Yii;
use yii\httpclient\Client;

/**
 * This is the model class for table "ms_terminal".
 *
 * @property int $terminalID
 * @property int $posType
 * @property string $terminalCode
 * @property int $branchID
 * @property string $deviceType
 * @property string $caption
 * @property int $statusID
 * @property string $activatedDate
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class Terminal extends ActiveRecord {

    public $apiUrl;
    public $batchID;
    public $username;
    public $responseData;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_terminal';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['terminalID', 'posType', 'terminalCode', 'statusID'], 'required'],
            [['activatedDate', 'createdDate', 'editedDate','apiUrl','responseData','username','batchID'], 'safe'],
            [['terminalID', 'posType', 'branchID', 'statusID'], 'integer'],
            [['terminalCode'], 'string', 'max' => 10],
            [['deviceType'], 'string', 'max' => 20],
            [['caption', 'createdBy', 'editedBy'], 'string', 'max' => 100],
        ];
    }

    public static function fetchTerminalList() {
        $branchID = Setting::getCurrentBranch();
        $terminalModel = Terminal::find()
            ->andWhere(['branchID' => $branchID])
            ->orderBy([
                'caption' => SORT_ASC,
                'terminalID' => SORT_ASC
            ])
            ->all();
        
        $newTerminalData = [];
        if ($terminalModel) {
            foreach ($terminalModel as $data) {
                $newTerminalData[$data->terminalID]['terminalID'] = $data->terminalID;
                $newTerminalData[$data->terminalID]['terminalCode'] = $data->terminalCode;
                $newTerminalData[$data->terminalID]['caption'] = $data->caption;
                $newTerminalData[$data->terminalID]['isActive'] = $data->activatedDate != null ? true : false;
                $newTerminalData[$data->terminalID]['deviceType'] = $data->deviceType;
            }
        }

        return array_values($newTerminalData);
    }

    public function saveTerminal() {
        try {
            $terminalModel = Terminal::findOne([
                'terminalCode' => $this->terminalCode,
            ]);
    
            if (!$terminalModel) {
                throw new Exception('Terminal data not found');
            } else {
                $beforeValue = null;
                if ($terminalModel->statusID == 47) {
                    $beforeValue = (object) array(
                        'terminalID' => $terminalModel->terminalID,
                        'caption' => $terminalModel->caption,
                        'deviceType' => $terminalModel->deviceType,
                        'activatedDate' => $terminalModel->activatedDate,
                    );
                }

                $terminalModel->statusID = 47;
                $terminalModel->caption = $this->caption;
                $terminalModel->deviceType = $this->deviceType;
                $terminalModel->activatedDate = date('Y-m-d H:i:s');
                if (!$terminalModel->save()) {
                    throw new Exception('Failed to save data terminal');
                } else {
                    $afterValue = [
                        'terminalID' => $terminalModel->terminalID,
                        'caption' => $terminalModel->caption,
                        'deviceType' => $terminalModel->deviceType,
                        'activatedDate' => $terminalModel->activatedDate,
                        'prevTerminal' => $beforeValue
                    ];
                    Logging::save($terminalModel->terminalCode, Logging::SAVE_TERMINAL, $afterValue);
                    return strval(strtotime($terminalModel->activatedDate));
                }
            }
        } catch (Exception $ex) {
            $this->addError('terminal', $ex->getMessage());
            return false;
        }
    }

    public function create()
    {
        try {
            $authUsername = Yii::$app->params['restUsername'];
            $authPassword = Yii::$app->params['restPassword'];
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/erp/terminal-code/create';
            $headers = [
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                'data-auth-username' =>  $this->getPasswordSalt()['username'],
                'data-auth-password' =>  $this->getPasswordSalt()['password'],
                'data-auth-salt' =>  $this->getPasswordSalt()['salt']
            ];
            $data =   [
                'branchID' => $this->branchID,
                'batchID' => $this->batchID,
                'statusID' => $this->statusID
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $data, $options);
    
            $this->responseData = $response->getData();
            if ($response->getIsOk()) {
                return true;
            } else {
                $this->addError($this->responseData["message"]);
                return false;
            }
        } catch (Exception $ex) {
            $errorMessage = "";
            self::transformExceptionMessage($ex, $errorMessage);
            $this->responseData["message"] = $errorMessage;
            return false;
        }
    }

    private function getPasswordSalt()
    {
        $posUser = PosUser::find()->where(['username' => $this->username])->one();
        return [
            'username' => $this->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public static function apiError($errorCode = 500, $errorMsg = 'Internal Server Error', $errorData = null)
    {
        $errorCode = $errorCode <= 100 || $errorCode >= 600 ? 500 : $errorCode;
        $errorResponse = [
            'path' => Yii::$app->request->absoluteUrl,
            'code' => $errorCode,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $errorMsg
        ];
        if ($errorData != null) {
            $errorResponse['data'] = $errorData;
        }

        Yii::$app->response->statusCode = $errorCode;
        return $errorResponse;
    }

    public static function transformExceptionMessage($exception, &$errorMessage, &$translate = 0) 
    {
        $exMessage = $exception->getMessage();
        if (strpos($exMessage, 'Curl error: #28 - Operation timed out') !== false) {
            $errorMessage = "Operation timed out, please try again";
        } elseif (strpos($exMessage, 'fopen') !== false || strpos($exMessage, 'Curl error: #6') !== false) {
            $errorMessage = "Please try again after checking your internet connection";
            $translate = 1;
        } else {
            $errorMessage = $exMessage;
        }
    }

}
