<?php
namespace app\models\forms;

use app\models\SalesPayment;
use app\models\ShiftLog;
use app\models\ShiftLogDetail;
use app\models\ShiftLogCash;
use app\models\ShiftLogMode;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;

/**
 * @property string $shiftInTotal
 * @property string $shiftOutTotal
 * @property string $shiftOutNotes
 * @property int $shiftID
 * @property int $shiftDetailID
 * 
 * PRIVATE
 * @property ShiftLog $shiftLogModel
 * @property ShiftLogDetail $shiftLogDetailModel
 * @property ShiftLogCash $shiftLogCashModel
 */
class ShiftCash extends Model
{
    const SCENARIO_SHIFT_IN = 'shift in';
    const SCENARIO_SHIFT_OUT = 'shift out';
    const SCENARIO_END_SHIFT = 'end shift';
    const SCENARIO_VALIDATE_IN = 'validate in';

    public $shiftInTotal;
    public $shiftOutTotal;
    public $closingNotes;
    public $shiftID;
    public $shiftDetailID;
    public $shiftLogModel;
    public $shiftLogDetailModel;
    public $shiftLogCashModel;
    public $shiftBaOnline;
    public $labelPrinted;
    public $username;
    public $differentStartUser;
    public $bypassShiftOutUser;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shiftInTotal'], 'required', 'on' => self::SCENARIO_SHIFT_IN],
            [['shiftOutTotal'], 'required', 'on' => self::SCENARIO_SHIFT_OUT],
            [['shiftID'], 'required', 'on' => self::SCENARIO_VALIDATE_IN],
            [['shiftInTotal', 'shiftOutTotal'], 'number'],
            [['username', 'differentStartUser', 'bypassShiftOutUser'], 'safe'],
            [['closingNotes'], 'string', 'max' => 200],
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_SHIFT_IN] = ['shiftInTotal'];
        $scenarios[self::SCENARIO_SHIFT_OUT] = ['shiftOutTotal', 'closingNotes', 'username'];
        $scenarios[self::SCENARIO_END_SHIFT] = [];
        $scenarios[self::SCENARIO_VALIDATE_IN] = ['shiftID'];

        return $scenarios;
    }

    public function save()
    {
        switch ($this->scenario) {
            case self::SCENARIO_SHIFT_IN:
                return $this->shiftIn();
            case self::SCENARIO_SHIFT_OUT:
                return $this->shiftOut();
            case self::SCENARIO_END_SHIFT:
                return $this->endShift();
            case self::SCENARIO_VALIDATE_IN:
                return $this->validateIn();
            default:
                return false;
        }
    }

    private function shiftIn()
    {
        $transaction = Yii::$app->db->beginTransaction();
        $result = NULL;
        $responseStatus = "ok";
        try {
            $message = "success";
            $this->shiftLogModel = ShiftLog::findActive();
            if (!$this->shiftLogModel) {
                $errorMessage = "Cannot find open shift";
                throw new Exception($errorMessage, [], 404);
            }

            $this->shiftLogCashModel = ShiftLogCash::findActive();
            if ($this->shiftLogCashModel) {
                $errorMessage = "Please close current open shift";
                $this->shiftLogCashModel = NULL;
                throw new Exception($errorMessage, [], 404);
            }

            $responseCode = "200";
            //@Notes: Shift Mode Checking
            $shiftMode = ShiftLogMode::getActiveShiftMode($this->shiftLogModel->shiftID);
            if ($shiftMode != "Regular" && $shiftMode == "Cash per Shift") {
                //@Notes: Start Shift Cash
                $this->shiftLogCashModel = new ShiftLogCash();
                $this->shiftLogCashModel->shiftID = $this->shiftLogModel->shiftID;
                $this->shiftLogCashModel->shiftNumber = ShiftLogCash::getShiftNumber($this->shiftLogModel->shiftID);
                $this->shiftLogCashModel->shiftInTime = new Expression('NOW()');
                $this->shiftLogCashModel->startingCash = $this->shiftInTotal;
                $this->shiftLogCashModel->shiftInUsername = $this->username;
                if (!$this->shiftLogCashModel->save()) {
                    Yii::error($this->shiftLogCashModel->errors);
                    throw new Exception('Failed to save shift cash');
                }
            }

            $responseData = [
                "shiftLogCashID" => $this->shiftLogCashModel->ID
            ];

            $result = SELF::apiResponse($responseStatus, $responseCode, $message, $responseData);
            $transaction->commit();
            return $result;
        } catch (Exception $ex) {
            $result = SELF::apiResponse($responseStatus, $ex->getCode(), $ex->getMessage(), null);
            $transaction->rollBack();
            return $result;
        }
    }

    private function shiftOut()
    {
        $transaction = Yii::$app->db->beginTransaction();
        $result = NULL;
        $responseStatus = "ok";
        $message = "success";
        try {
            $errorMessage = NULL;
            $this->shiftLogModel = ShiftLog::findActive();
            if (!$this->shiftLogModel) {
                $errorMessage = "Cannot find open shift";
                throw new Exception($errorMessage, [], 404);
            }

            $this->shiftLogCashModel = ShiftLogCash::findActive();
            if (!$this->shiftLogCashModel) {
                $errorMessage = "Cannot find current open shift";
                throw new Exception($errorMessage, [], 404);
            }

            if ($this->shiftLogCashModel->shiftInUsername != $this->username && !$this->bypassShiftOutUser) {
                $this->differentStartUser = true;
                $errorMessage = "Different shift in and shift out user";
            }

            if (!$errorMessage) {
                $responseCode = "200";
                //@Notes: Shift Mode Checking
                $shiftMode = ShiftLogMode::getActiveShiftMode($this->shiftLogModel->shiftID);
                if ($shiftMode != "Regular" && $shiftMode == "Cash per Shift") {
                    //@Notes: Shift Log Detail
                    $this->shiftLogDetailModel = new ShiftLogDetail();
                    $this->shiftLogDetailModel->shiftID = $this->shiftLogModel->shiftID;
                    $this->shiftLogDetailModel->shiftTime = new Expression('NOW()');
                    $this->shiftLogDetailModel->shiftUsername = $this->username;
                    if (!$this->shiftLogDetailModel->save()) {
                        Yii::error($this->shiftLogDetailModel->errors);
                        throw new Exception('Failed to save shift log detail');
                    }

                    //@Notes: End Shift Log Cash
                    $this->shiftLogCashModel = ShiftLogCash::findActive();

                    $totalGrandTotalShift = SalesPayment::getTotalGrandTotalShift($this->shiftLogCashModel->ID,
                        $this->shiftLogModel);
                    $totalNonCashShift = SalesPayment::getTotalNonCashShift($this->shiftLogCashModel->ID,
                            $this->shiftLogModel);
                    $totalCashShift = $totalGrandTotalShift - $totalNonCashShift;

                    $this->shiftLogCashModel->shiftOutTime = $this->shiftLogDetailModel->shiftTime;
                    $this->shiftLogCashModel->endingCash = $this->shiftOutTotal;
                    $this->shiftLogCashModel->systemCashReceivedTotal = $totalCashShift;
                    $this->shiftLogCashModel->closingNotes = $this->closingNotes;
                    $this->shiftLogCashModel->shiftOutUsername = $this->username;
                    if (!$this->shiftLogCashModel->save()) {
                        Yii::error($this->shiftLogCashModel->errors);
                        throw new Exception('Failed to save shift log cash');
                    }
                    $this->shiftDetailID = $this->shiftLogCashModel->ID;

                    Logging::save(strval($this->shiftLogDetailModel->shiftID),
                        Logging::END_SHIFT, $this->getAttributes());
                }
            } else {
                $responseCode = "404";
                $message = $errorMessage;
            }

            $responseData = [
                "shiftLogCashID" => $this->shiftDetailID,
                "differentStartUser" => $this->differentStartUser,
                "bypassShiftOutUser" => $this->bypassShiftOutUser
            ];

            $result = SELF::apiResponse($responseStatus, $responseCode, $message, $responseData);
            $transaction->commit();
            return $result;
        } catch (Exception $ex) {
            $result = SELF::apiResponse($responseStatus, $ex->getCode(), $ex->getMessage(), null);
            $transaction->rollBack();
            return $result;
        }
    }

    private function validateIn()
    {
        $transaction = Yii::$app->db->beginTransaction();
        $result = NULL;
        $responseStatus = "ok";
        try {
            $responseCode = "200";
            $message = "success";
            $this->shiftLogModel = ShiftLog::findActive();
            if (!$this->shiftLogModel) {
                $errorMessage = "Cannot find open shift";
                throw new Exception($errorMessage, [], 404);
            }

            $this->shiftLogCashModel = ShiftLogCash::findActive();
            if (!$this->shiftLogCashModel) {
                $errorMessage = "Cannot find current open shift";
                $this->shiftLogCashModel = NULL;
                throw new Exception($errorMessage, [], 404);
            }

            $responseData = [
                "shiftLogCashID" => $this->shiftLogCashModel->ID
            ];

            $result = SELF::apiResponse($responseStatus, $responseCode, $message, $responseData);
            $transaction->commit();
            return $result;
        } catch (Exception $ex) {
            $result = SELF::apiResponse($responseStatus, $ex->getCode(), $ex->getMessage(), null);
            $transaction->rollBack();
            return $result;
        } 
    }

    public static function apiResponse($responseStatus = "ok", $responseCode = 200, $message = 'success', $responseData = null)
    {
        $responseCode = $responseCode <= 100 || $responseCode >= 600 ? 500 : $responseCode;
        $response = [
            'path' => Yii::$app->request->absoluteUrl,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $responseStatus,
            'code' => $responseCode,
            'message' => $message
        ];
        if ($responseCode != null) {
            $response['result'] = $responseData;
        }

        Yii::$app->response->statusCode = $responseCode;
        return $response;
    }

}
