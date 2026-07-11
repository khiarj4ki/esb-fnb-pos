<?php

namespace app\models\forms;

use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use ZipArchive;

class UpdateTableSide extends Model {
    public $tableSideVersion;
    public $tableSideBaseHref;
    public $flagCheckTableSide = false;
    public $errorMessage;
    public $responseMessage;

    public function rules() {
        return [
            [['tableSideVersion'], 'required'],
            [['tableSideBaseHref', 'flagCheckTableSide'], 'safe'],
        ];
    }

    public static function getMainDirectory($path) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);
        $projectPath = join(DIRECTORY_SEPARATOR, $dirs);
        return $projectPath . $path;
    }

    public static function getDownloadDirectory($path) {
        return self::getMainDirectory($path) . DIRECTORY_SEPARATOR . 'update-esb-fnb-tableside';
    }

    public function checkUpdate() {
        try {
            $branchID = Setting::getCurrentBranch();
            $apiVersion = 'esb_api';
            $tableSideVersion = $this->tableSideVersion;
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/check-update-table-side';
  
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl;
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $datas =   [
                'branchID' => $branchID,
                'tableSideVersion' => $tableSideVersion
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
                
                if ($this->flagCheckTableSide) {
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
            Yii::error($ex);
            $exceptionMessage = $ex->getMessage();
            if (strpos($exceptionMessage, 'fopen(): php_network_getaddresses') !== false) $exceptionMessage = 'No internet connection';
            $this->errorMessage = [
                'code' => 500,
                'message' => $exceptionMessage
            ];

            if ($this->flagCheckTableSide) {
                $currentVersion = self::getCurrentVersion($this->tableSideVersion);
                self::setLoggingFailUpdate($currentVersion, null, $exceptionMessage);
            }
        }
    }

    public function applyUpdate() {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectory($this->tableSideBaseHref);
        try {
            $newVersion = $this->checkUpdate();
            if (!$newVersion) {
                $this->handleErrorAction();
                return false;
            }

            if (!$this->processFile($newVersion, 0, $this->tableSideBaseHref)) {
                $latestVersion = $newVersion && $newVersion['name'] ? $newVersion['name'] : null;
                $this->handleErrorAction($latestVersion);
                return false;
            }

            // @notes: Temporary Rename
            self::deleteUnusedFiles($mainFilePath, 0);

            $latestVersionName = $newVersion['name'];
            $downloadDirectory = self::getDownloadDirectory($this->tableSideBaseHref);
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

            $currentVersion = self::getCurrentVersion($this->tableSideVersion);
            if ($updateSuccess) {
                // @notes: Delete
                self::deleteUnusedFiles($mainFilePath, 1);

                $versionUpdated = $newVersion['id'] . "|" . $newVersion['name'];
                self::setTableSideVersion($versionUpdated);

                if (isset($currentVersion) && $currentVersion !== '') {
                    $dataLogging = [
                        'beforeUpdate' => $currentVersion[1],
                        'afterUpdate' => $latestVersionName
                    ];
    
                    Logging::save('-', Logging::UPDATE_TABLESIDE_VERSION, $dataLogging);
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

    public function processFile($version, $attempt = 0, $tableSideBaseHref) {
        $md5 = $version["downloadMd5"];
        $downloadUrl = $version['downloadUrl'];
        $versionName = $version['name'];
        $downloadDirectory = self::getDownloadDirectory($tableSideBaseHref);
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
                return self::processFile($version, $attempt, $tableSideBaseHref);
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

    private static function getCurrentVersion($tableSideVersion) {
        return $tableSideVersion ? explode("|", $tableSideVersion) : '';
    }

    public static function setTableSideVersion($latestVersion, $flagManualUpdate = false, $productType = null) {
        $key1 = 'Local Setting';
        $key2 = 'Tableside Version';
        Setting::setNewVersion($key1, $key2, $latestVersion, $flagManualUpdate, $productType);
    }

    public function handleErrorAction($latestVersion = null, $errorMessage = null) {
        $currentVersion = self::getCurrentVersion($this->tableSideVersion);
        $responseMessage = $this->responseMessage ? $this->responseMessage['message'] : $errorMessage;
        self::setLoggingFailUpdate($currentVersion, $latestVersion, $responseMessage);
    }

    private static function setLoggingFailUpdate($currentVersion, $latestVersionName, $responseMessage) {
        if (isset($currentVersion) && $currentVersion !== '') {
            $dataLogging = [
                'beforeUpdate' => $currentVersion[1],
                'afterUpdate' => $latestVersionName,
                'errorMessage' => $responseMessage ? $responseMessage : null
            ];

            Logging::save('-', Logging::FAIL_UPDATE_TABLESIDE_VERSION, $dataLogging);
        }
    }
}
