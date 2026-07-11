<?php

namespace app\models\forms;

use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use ZipArchive;

class UpdateKiosk extends Model {
    public $kioskVersion;
    public $kioskBaseHref;
    public $flagCheckKiosk = false;
    public $errorMessage;
    public $responseMessage;

    public function rules() {
        return [
            [['kioskVersion'], 'required'],
            [['kioskBaseHref', 'flagCheckKiosk'], 'safe'],
        ];
    }

    public static function getMainDirectory($path) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);
        $projectPath = join(DIRECTORY_SEPARATOR, $dirs);
        return $projectPath . $path;
    }

    public static function getMainDirectoryKiosk($path = null) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);

        $projectPath = join(DIRECTORY_SEPARATOR, $dirs);
        $projectPath = str_replace('\esb-fnb-pos', '',$projectPath );

        return $projectPath . $path;
    }

    public static function getDownloadDirectory($path) {
        return self::getMainDirectoryKiosk($path) . DIRECTORY_SEPARATOR . 'update-esb-fnb-kiosk';
    }

    public function checkUpdate() {
        try {
            $branchID = Setting::getCurrentBranch();
            $apiVersion = 'esb_api';
            $kioskVersion = $this->kioskVersion;
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
    
            $versionID = NULL;
            $versionName = NULL;
            $responseBody = $response->getData();
            if ($response->getIsOk()) {
                $versionID = $responseBody['id'];
                $versionName = $responseBody['name'];
            } else {
                $message = 'Failed to fetch data';
                $this->errorMessage = [
                    'code' => 400,
                    'message' => $message
                ];
    
                if ($this->flagCheckKiosk) {
                    self::handleErrorAction(null, $message);
                }
            }
    
            if (!empty($versionID) || !empty($versionName)) {
                return $responseBody;
            } else {
                $this->responseMessage = [
                    'message' => 'Version not found!' 
                ];
    
                return $responseBody;
            }
        } catch (Exception $ex) {
          
            $exceptionMessage = $ex->getMessage();
            if (strpos($exceptionMessage, 'fopen(): php_network_getaddresses') !== false) $exceptionMessage = 'No internet connection';
            $this->errorMessage = [
                'code' => 500,
                'message' => $exceptionMessage
            ];

            if ($this->flagCheckKiosk) {
                $currentVersion = self::getCurrentVersion($this->kioskVersion);
                self::setLoggingFailUpdate($currentVersion, null, $exceptionMessage);
            }
        }
    }

    public function applyUpdate() {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectoryKiosk($this->kioskBaseHref);
        try {
            $newVersion = $this->checkUpdate();
            if (!$newVersion) {
                $this->handleErrorAction();
                return false;
            }

            if (!$this->processFile($newVersion, 0, $this->kioskBaseHref)) {
                $latestVersion = $newVersion && $newVersion['name'] ? $newVersion['name'] : null;
                $this->handleErrorAction($latestVersion);
                return false;
            }

            // @notes: Temporary Rename
            self::deleteUnusedFiles($mainFilePath, 0);

            $latestVersionName = $newVersion['name'];
            $downloadDirectory = self::getDownloadDirectory($this->kioskBaseHref);
            $fileName = basename($newVersion['downloadUrl']);
            $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $latestVersionName . DIRECTORY_SEPARATOR . $fileName;

            $zip = new ZipArchive;
            $res = $zip->open($filePath);
            if ($res === true) {
                $zip->extractTo("$mainFilePath");
                $zip->close();
            } else {
                $updateSuccess = false;
            }

            $currentVersion = self::getCurrentVersion($this->kioskVersion);
            if ($updateSuccess) {
                // @notes: Delete
                self::deleteUnusedFiles($mainFilePath, 1);

                $versionUpdated = $newVersion['id'] . "|" . $newVersion['name'];
                $latestVersion = self::getCurrentVersion($versionUpdated);
                self::setKioskVersion($versionUpdated);

                if (isset($currentVersion) && $currentVersion !== '') {
                    $latestVersionName = $versionUpdated;
                    self::setLoggingSuccess($currentVersion, $latestVersion);
                }
            } else {
                $responseMessage = 'Failed to open file';
                self::setLoggingFailUpdate($currentVersion, $latestVersionName, $responseMessage);
                throw new Exception("Restore deleted unused files");
            }

            return $updateSuccess;
        } catch (Exception $ex) {
            // @notes Restore
            self::deleteUnusedFiles($mainFilePath, 2);
            Yii::error($ex);
            return false;
        }
    }

    public function downloadUpdateKioskVersion() {
    
        $mainFilePath = self::getMainDirectoryKiosk($this->kioskBaseHref);
        try {
            $newVersion = $this->checkUpdate();
            if (!$newVersion) {
                $this->handleErrorAction();
                return false;
            }

            if (!$this->processFile($newVersion, 0, $this->kioskBaseHref)) {
                $latestVersion = $newVersion && $newVersion['name'] ? $newVersion['name'] : null;
                $this->handleErrorAction($latestVersion);
                return false;
            }

            return true;
        } catch (Exception $ex) {
            // @notes Restore
            self::deleteUnusedFiles($mainFilePath, 2);
            return false;
        }
    }

    private static function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    public function processFile($version, $attempt = 0, $kioskBaseHref) {
        $md5 = $version["downloadMd5"];
        $downloadUrl = $version['downloadUrl'];
        $versionName = $version['name'];
        $downloadDirectory = self::getDownloadDirectory($kioskBaseHref);
        $versionDirectory = $downloadDirectory . DIRECTORY_SEPARATOR . $versionName;
        if (!file_exists($downloadDirectory)) {
            mkdir($downloadDirectory);
        }

        if (!file_exists($versionDirectory)) {
            mkdir($versionDirectory);
        }

        $fileName = basename($downloadUrl);
        $fileSavePath = $versionDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($fileSavePath)) {
            $this->downloadFile($downloadUrl, $fileSavePath);
        }

        if (strtolower(md5_file($fileSavePath)) == strtolower($md5)) {
            return true;
        } else {
            if (file_exists($fileSavePath)) {
                unlink($fileSavePath);
            }
            if (file_exists($versionDirectory)) {
                rmdir($versionDirectory);
            }

            $attempt += 1;
            if ($attempt > 5) {
                $errorMessage = 'No internet connection';
                $this->errorMessage = [
                    'code' => 400,
                    'message' => $errorMessage
                ];

                $this->responseMessage = [
                    'message' => $errorMessage
                ];

                $this->handleErrorAction($versionName);
                
                return false;
            } else {
                return self::processFile($version, $attempt, $kioskBaseHref);
            }
        }
    }

    public function downloadFile($url, $path) {
        try {
            $newfname = $path;
            $file = fopen($url, 'rb');
            if ($file) {
                $newf = fopen($newfname, 'wb');
                if ($newf) {
                    if (!feof($file)) {
                        while (!feof($file)) {
                            fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
                        }
                    } else {
                        $errorMessage = 'Maximum execution time';
                        $this->responseMessage = [
                            'message' => $errorMessage
                        ];

                        throw new Exception($errorMessage);
                    }
                }
            }
            if ($file) {
                fclose($file);
            }
            if ($newf) {
                fclose($newf);
            }
        } catch (\Exception $ex) {
            Yii::error($ex);
        }
    }


    private static function deleteUnusedFiles($rootPath, $action = 0) {
        $rootFiles = scandir($rootPath);
        foreach ($rootFiles as $rootFile) {
            $filePath = $rootPath . DIRECTORY_SEPARATOR . $rootFile;
            // 0 = rename, 1 = delete, 2 = restore
            if ($action === 0) {
                if (
                    is_file($filePath) &&
                    ((substr($rootFile, 0, strlen('main')) === 'main') ||
                        (substr($rootFile, 0, strlen('polyfills')) === 'polyfills') ||
                        (substr($rootFile, 0, strlen('runtime')) === 'runtime') ||
                        (substr($rootFile, 0, strlen('scripts')) === 'scripts') ||
                        (substr($rootFile, 0, strlen('styles')) === 'styles'))
                ) {
                    rename($filePath, $filePath . '__DELETE');
                }
            } else if ($action === 1) {
                if (fnmatch("*__DELETE", $rootFile)) {
                    unlink($filePath);
                }
            } else if ($action === 2) {
                if (fnmatch("*__DELETE", $rootFile)) {
                    rename($filePath, str_replace('__DELETE', '', $filePath));
                }
            }
        }
    }

    public static function getCurrentVersion($kioskVersion) {
        return $kioskVersion ? explode("|", $kioskVersion) : '';
    }

    public static function setKioskVersion($latestVersion, $flagManualUpdate = false, $productType = null) {
        $key1 = 'Local Setting';
        $key2 = 'Kiosk Version';
        Setting::setNewVersion($key1, $key2, $latestVersion, $flagManualUpdate, $productType);
    }

    public function handleErrorAction($latestVersion = null, $errorMessage = null) {
        $currentVersion = self::getCurrentVersion($this->kioskVersion);
        $responseMessage = $this->responseMessage ? $this->responseMessage['message'] : $errorMessage;
        self::setLoggingFailUpdate($currentVersion, $latestVersion, $responseMessage);
    }

    public static function setLoggingSuccess($currentVersion, $latestVersion, $loggingEvent = null) {
        $dataLogging = [
            'beforeUpdate' => $currentVersion ? $currentVersion[1] : null,
            'afterUpdate' => $latestVersion ? $latestVersion[1] : null
        ];

        Logging::save('-', $loggingEvent ? $loggingEvent : Logging::UPDATE_KIOSK_VERSION, $dataLogging);
    }

    private static function setLoggingFailUpdate($currentVersion, $latestVersionName, $responseMessage) {
        if (isset($currentVersion) && $currentVersion !== '') {
            $dataLogging = [
                'beforeUpdate' => $currentVersion[1],
                'afterUpdate' => $latestVersionName,
                'errorMessage' => $responseMessage ? $responseMessage : null
            ];

            Logging::save('-', Logging::FAIL_UPDATE_KIOSK_VERSION, $dataLogging);
        }
    }
}
