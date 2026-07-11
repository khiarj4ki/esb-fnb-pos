<?php

namespace app\models;

use app\services\http_helper\HttpHelperService;
use Yii;
use yii\db\ActiveRecord;
use yii\httpclient\Client;
use yii\httpclient\Exception;

/**
 * This is the model class for table "ms_posversion".
 *
 * @property int $posVersionID
 * @property string $versionName
 * @property string $downloadUrl
 * @property string $downloadMd5
 * @property string $query
 * @property string $deletedFiles
 */
class PosVersion extends ActiveRecord {
    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'ms_posversion';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['posVersionID', 'versionName', 'downloadUrl', 'downloadMd5'], 'required'],
            [['query', 'deletedFiles'], 'string'],
            [['versionName'], 'string', 'max' => 45],
            [['downloadUrl'], 'string', 'max' => 300],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'posVersionID' => 'Pos Version ID',
            'versionName' => 'Version Name',
            'downloadUrl' => 'Download Url',
            'query' => 'Query',
            'deletedFiles' => 'Deleted Files',
        ];
    }

    public static function getRawAppVersion() {
        $versionFile = Yii::$app->basePath . "/config/updater.ver";
        $version = file_get_contents($versionFile);
        return $version;
    }

    public static function getAppVersion() {
        $versionFile = Yii::$app->basePath . "/config/updater.ver";
        $version = explode("|", file_get_contents($versionFile));
        return ["id" => $version[0], "name" => $version[1]];
    }

    public static function setAppVersion($id, $name) {
        $versionFile = Yii::$app->basePath . "/config/updater.ver";
        return file_put_contents($versionFile, "$id|$name");
    }

    public static function getLatestPosData() {
        try {
            
            $branchID = Setting::getCurrentBranch();
            $apiVersion = 'esb_api';
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/get-latest-version';
            
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl;
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $data =   ['branchID' => $branchID];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $data, $options);

            if ($response->getIsOk()) {
                $latestPosVersion = $response->getData();
                return $latestPosVersion;
            } else {
                Yii::warning($response->getData());
                throw new Exception('Failed to fetch data');
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function getLatestVersion() {
        $branchID = Setting::getCurrentBranch();
        $apiVersion = 'esb_api';
        $apiKey = Setting::getApiKey();
        $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/get-latest-version';

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $apiUrl;
        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $data =   ['branchID' => $branchID];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $data, $options);

        $versionID = null;
        $versionName = null;
        if ($response->getIsOk()) {
            $versionID = $response->getData()['id'];
            $versionName = $response->getData()['name'];
        } else {
            return null;
        }

        return $versionName;
    }

    
    public static function getLatestPosDataKiosk($kioskVersion) {
        try {
            $branchID = Setting::getCurrentBranch();
            $apiVersion = 'esb_api';
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/check-update-kiosk';
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl;
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $datas =   [
                'branchID' => $branchID,
                'kioskVersion' => $kioskVersion
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getIsOk()) {
                $latestPosVersion = $response->getData();
            } else {
                throw new Exception('Failed to fetch data');
            }

            return $latestPosVersion;
        } catch (Exception $ex) {
            return false;
        }
    }

}
