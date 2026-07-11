<?php

namespace app\models\forms;

use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use ZipArchive;

class UpdateQds extends Model {
    public $qdsVersion;
    public $qdsBaseHref;
    public $flagCheckQds = false;
    public $errorMessage;
    public $responseMessage;

    public function rules() {
        return [
            [['qdsVersion'], 'required'],
            [['qdsBaseHref', 'flagCheckQds'], 'safe'],
        ];
    }

    public static function getMainDirectoryQds($path = null) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);

        $projectPath = join(DIRECTORY_SEPARATOR, $dirs);
        $projectPath = str_replace('\esb-fnb-pos', '',$projectPath );

        return $projectPath . $path;
    }

    public static function getDownloadDirectory($path) {
        return self::getMainDirectoryQds($path) . DIRECTORY_SEPARATOR . 'update-esb-qds';
    }

    public function checkUpdate() {

        try {
            $branchID = Setting::getCurrentBranch();
            $apiVersion = 'esb_api';
            $qdsVersion = $this->qdsVersion;
            
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/check-update-qds';
     
            $httpService = new HttpHelperService();
            $url = $apiUrl;
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $datas =   [
                'branchID' => $branchID,
                'qdsVersion' => $qdsVersion
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
    
                if ($this->flagCheckQds) {
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

            if ($this->flagCheckQds) {
                $currentVersion = self::getCurrentVersion($this->qdsVersion);
                self::setLoggingFailUpdate($currentVersion, null, $exceptionMessage);
            }
        }
    }

    public function applyUpdate() {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectoryQds($this->qdsBaseHref);

        try {
            $newVersion = $this->checkUpdate();
            if (!$newVersion) {
                $this->handleErrorAction();
                return false;
            }

            if (!$this->processFile($newVersion, 0, $this->qdsBaseHref)) {
                $latestVersion = $newVersion && $newVersion['name'] ? $newVersion['name'] : null;
                $this->handleErrorAction($latestVersion);
                return false;
            }

            self::deleteUnusedFiles($mainFilePath, 0);

            $latestVersionName = $newVersion['name'];
            $downloadDirectory = self::getDownloadDirectory($this->qdsBaseHref);
            $fileName = basename($newVersion['downloadUrl']);
            $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $latestVersionName . DIRECTORY_SEPARATOR . $fileName;

            //@notes: Unzip file update
            $zip = new ZipArchive;
            $res = $zip->open($filePath);
            
            if ($res === true) {
                $zip->extractTo("$mainFilePath");
                $zip->close();
            } else {
                $updateSuccess = false;
            }

            $currentVersion = self::getCurrentVersion($this->qdsVersion);
            if ($updateSuccess) {
                // @notes: Delete
                self::deleteUnusedFiles($mainFilePath, 1);

                //@notes: Save new version QDS to ms_setting
                $versionUpdated = $newVersion['id'] . "|" . $newVersion['name'];
                $latestVersion = self::getCurrentVersion($versionUpdated);
                self::setQdsVersion($versionUpdated);

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

    public function processFile($version, $attempt = 0, $qdsBaseHref) {
        $md5 = $version["downloadMd5"];
        $downloadUrl = $version['downloadUrl'];
        $versionName = $version['name'];
        $downloadDirectory = self::getDownloadDirectory($qdsBaseHref);

        //@notes: Make folder for file update
        $versionDirectory = $downloadDirectory . DIRECTORY_SEPARATOR . $versionName;
        if (!file_exists($downloadDirectory)) {
            mkdir($downloadDirectory);
        }

        if (!file_exists($versionDirectory)) {
            mkdir($versionDirectory);
        }

        //@notes: Download file update
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
                return self::processFile($version, $attempt, $qdsBaseHref);
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
            //@notes: 0 = rename, 1 = delete, 2 = restore
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

    public static function getCurrentVersion($qdsVersion) {
        return $qdsVersion ? explode("|", $qdsVersion) : '';
    }

    public static function setQdsVersion($latestVersion, $flagManualUpdate = false, $productType = null) {
        $key1 = 'Local Setting';
        $key2 = 'Qds Version';
        Setting::setNewVersion($key1, $key2, $latestVersion, $flagManualUpdate, $productType);
    }

    public function handleErrorAction($latestVersion = null, $errorMessage = null) {
        $currentVersion = self::getCurrentVersion($this->qdsVersion);
        $responseMessage = $this->responseMessage ? $this->responseMessage['message'] : $errorMessage;
        self::setLoggingFailUpdate($currentVersion, $latestVersion, $responseMessage);
    }

    public static function setLoggingSuccess($currentVersion, $latestVersion, $loggingEvent = null) {
        $dataLogging = [
            'beforeUpdate' => $currentVersion ? $currentVersion[1] : null,
            'afterUpdate' => $latestVersion ? $latestVersion[1] : null
        ];

        Logging::save('-', $loggingEvent ? $loggingEvent : Logging::UPDATE_QDS_VERSION, $dataLogging);
    }

    private static function setLoggingFailUpdate($currentVersion, $latestVersionName, $responseMessage) {
        if (isset($currentVersion) && $currentVersion !== '') {
            $dataLogging = [
                'beforeUpdate' => $currentVersion[1],
                'afterUpdate' => $latestVersionName,
                'errorMessage' => $responseMessage ? $responseMessage : null
            ];

            Logging::save('-', Logging::FAIL_UPDATE_QDS_VERSION, $dataLogging);
        }
    }
}
