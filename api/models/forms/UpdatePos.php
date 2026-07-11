<?php

namespace app\models\forms;

use app\models\PosVersion;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use ZipArchive;

class UpdatePos extends Model {
    public $posAppName;
    public $startUpdate;
    public $latestVersion;
    public $currentVersion;
    public $pathFolder;

    public function rules() {
        return [
            [['posAppName'], 'required'],
            [['latestVersion','currentVersion','pathFolder'], 'safe']
        ];
    }

    public static function getMainDirectory($path = null) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);
        $mainPath = join(DIRECTORY_SEPARATOR, $dirs);
        //default path
        $resultPath = $mainPath;
        // remove folder name pos
        if($path === "/esb-kiosk" || $path === "/esb-fnb-ods"){
            $fileNameRemove = end($dirs);
            $path = $mainPath . $path;
            $resultPath = str_replace($fileNameRemove, '',$path );
        }
        
        return $resultPath;
    }

    public static function getMainDirectoryOdsKiosk($path = null) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);
        $mainPath = join(DIRECTORY_SEPARATOR, $dirs);
        //default path
        $resultPath = $mainPath;
        // remove folder name pos
        if($path === "/esb-kiosk" || $path === "/esb-fnb-ods"){
            $fileNameRemove = end($dirs);
            $path = $mainPath . $path;
            $resultPath = str_replace($fileNameRemove, '',$path );
        }
        
        return $resultPath;
    }

    public static function getDownloadDirectory() {
        return self::getMainDirectory() . DIRECTORY_SEPARATOR . 'update-esb-fnb-pos';
    }

    public static function getDownloadDirectoryKiosk($path) {
        $mainPath = self::getMainDirectory($path);
        return $mainPath . DIRECTORY_SEPARATOR . 'update-esb-fnb-kiosk';
    }

    public static function getDownloadDirectoryOds($path) {
        $mainPath = self::getMainDirectory($path);
        return  $mainPath . DIRECTORY_SEPARATOR . 'update-esb-fnb-ods';
    }

    public static function hasNewVersion() {
        $branchID = Setting::getCurrentBranch();
        $apiVersion = 'esb_api';
        $appVersion = PosVersion::getAppVersion()["id"] . '|' . PosVersion::getAppVersion()["name"];
        $apiKey = Setting::getApiKey();
        $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/check-update';

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $apiUrl;
        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $datas =   [
            'branchID' => $branchID,
            'versionID' => $appVersion
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        $hasNewVersion = false;
        if ($response->getIsOk()) {
            if (isset($response->getData()['id']) &&
                isset($response->getData()['name'])) {
                $hasNewVersion = true;
            }
        } else {
            return false;
        }

        return $hasNewVersion;
    }

    public function applyUpdate() {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectory();
        try {
            if (!self::hasNewVersion()) {
                return false;
            }

            $latestPosVersion = PosVersion::getLatestPosData();
            $angularBaseDir = $mainFilePath . DIRECTORY_SEPARATOR;
            // @notes: Temporary Rename
            self::deleteUnusedFiles($angularBaseDir . 'en', 0);
            self::deleteUnusedFiles($angularBaseDir . 'id', 0);

            $latestVersionID = $latestPosVersion['id'];
            $latestVersionName = $latestPosVersion['name'];
            $downloadDirectory = self::getDownloadDirectory();
            $fileName = basename($latestPosVersion['downloadUrl']);
            $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $latestVersionName . DIRECTORY_SEPARATOR . $fileName;

            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                $zip->extractTo($mainFilePath);
                $zip->close();
            } else {
                $updateSuccess = false;
            }

            $currentVersion = PosVersion::getAppVersion();
            $currentVersionName = str_replace(array("\n", "\r"), '', $currentVersion["name"]);
            if ($updateSuccess) {
                $yiiLocation = Yii::$app->basePath . '/yii';
                exec("php $yiiLocation migrate --interactive=0");
                PosVersion::setAppVersion($latestVersionID, $latestVersionName);
                Yii::$app->cache->flush();

                if ($currentVersion) {
                    $dataLogging = [
                        'date' => [
                            'startUpdate' => $this->startUpdate,
                            'finishUpdate' => date('Y-m-d H:i:s')
                        ],
                        'beforeUpdate' => $currentVersionName,
                        'afterUpdate' => $latestVersionName
                    ];
    
                    Logging::save('-', Logging::UPDATE_POS_VERSION, $dataLogging);
                }
                
                // @notes: Delete
                self::deleteUnusedFiles($angularBaseDir . 'en', 1);
                self::deleteUnusedFiles($angularBaseDir . 'id', 1);
                self::removeFileDownloaded($filePath);
            } else {
                if ($currentVersion) {
                    $errorMessage = 'Failed to open file';
                    self::setLoggingFailed($currentVersionName, $latestVersionName, $errorMessage, $this->startUpdate);
                }

                throw new Exception("Restore deleted unused files");
            }

            return $updateSuccess;
        } catch (Exception $ex) {
            // @notes Restore
            self::deleteUnusedFiles($angularBaseDir . 'en', 2);
            self::deleteUnusedFiles($angularBaseDir . 'id', 2);
            self::removeFileDownloaded($filePath);
            Yii::error($ex);
            return false;
        }
    }

    public function applyUpdateKiosk() {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectoryOdsKiosk($this->pathFolder);
        try {

            $latestVersion = $this->latestVersion;

            // @notes: Temporary Rename
            self::deleteUnusedFiles($mainFilePath, 0);
            
            $latestVersionID = $latestVersion['id'];
            $latestVersionName = $latestVersion['name'];
            $downloadDirectory = self::getDownloadDirectoryKiosk($this->pathFolder);
            $fileName = basename($latestVersion['downloadUrl']);
            $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $latestVersionName . DIRECTORY_SEPARATOR . $fileName;

            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                $zip->extractTo($mainFilePath);
                $zip->close();
            } else {
                $updateSuccess = false;
            }

            $currentVersion = $this->currentVersion;
            $currentVersionName = str_replace(array("\n", "\r"), '', $currentVersion);
            if ($updateSuccess) {
                if ($currentVersion) {
                    $dataLogging = [
                        'date' => [
                            'startUpdate' => $this->startUpdate,
                            'finishUpdate' => date('Y-m-d H:i:s')
                        ],
                        'beforeUpdate' => $currentVersionName,
                        'afterUpdate' => $latestVersionName
                    ];
    
                    Logging::save('-', Logging::UPDATE_KIOSK_VERSION, $dataLogging);
                }

                // @update ms_setting
                $versionUpdated = $latestVersionID . "|" . $latestVersionName;
                UpdateKiosk::setKioskVersion($versionUpdated);
  
                // @notes: Delete
                self::deleteUnusedFiles($mainFilePath, 1);
                self::removeFileDownloaded($filePath);
            } else {

                if ($currentVersion) {
                    $errorMessage = 'Failed to open file';
                    self::setLoggingFailed($currentVersionName, $latestVersionName, $errorMessage, $this->startUpdate);
                }
                throw new Exception("Restore deleted unused files");
            }

            return $updateSuccess;
        } catch (Exception $ex) {
            // @notes Restore
            self::deleteUnusedFiles($mainFilePath, 2);
            self::removeFileDownloaded($filePath);
            return false;
        }
    }

    public function applyUpdateOds() {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectoryOdsKiosk($this->pathFolder);
        try {

            $latestVersion = $this->latestVersion;

            // @notes: Temporary Rename
            self::deleteUnusedFiles($mainFilePath, 0);

            $latestVersionID = $latestVersion['id'];
            $latestVersionName = $latestVersion['name'];
            $downloadDirectory = self::getDownloadDirectoryOds($this->pathFolder);
            $fileName = basename($latestVersion['downloadUrl']);
            $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $latestVersionName . DIRECTORY_SEPARATOR . $fileName;

            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                $zip->extractTo($mainFilePath);
                $zip->close();
            } else {
                $updateSuccess = false;
            }

            $currentVersion = $this->currentVersion;
            $currentVersionName = str_replace(array("\n", "\r"), '', $currentVersion);
            if ($updateSuccess) {
                if ($currentVersion) {
                    $dataLogging = [
                        'date' => [
                            'startUpdate' => $this->startUpdate,
                            'finishUpdate' => date('Y-m-d H:i:s')
                        ],
                        'beforeUpdate' => $currentVersionName,
                        'afterUpdate' => $latestVersionName
                    ];
    
                    Logging::save('-', Logging::UPDATE_ODS_VERSION, $dataLogging);
                }
                // @update ms_setting
                $versionUpdated = $latestVersionID . "|" . $latestVersionName;
                UpdateOds::setOdsVersion($versionUpdated);

                // @notes: Delete
                self::deleteUnusedFiles($mainFilePath, 1);
                self::removeFileDownloaded($filePath);
            } else {
                if ($currentVersion) {
                    $errorMessage = 'Failed to open file';
                    self::setLoggingFailed($currentVersionName, $latestVersionName, $errorMessage, $this->startUpdate);
                }

                throw new Exception("Restore deleted unused files");
            }

            return $updateSuccess;
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

    private static function deleteOldAppFiles($rootPath) {
        $rootFiles = scandir($rootPath);
        foreach ($rootFiles as $rootFile) {
            $filePath = "$rootPath/$rootFile";
            if (is_file($filePath) &&
                ((substr($rootFile, 0, strlen('main')) === 'main') ||
                (substr($rootFile, 0, strlen('polyfills')) === 'polyfills') ||
                (substr($rootFile, 0, strlen('runtime')) === 'runtime') ||
                (substr($rootFile, 0, strlen('scripts')) === 'scripts') ||
                (substr($rootFile, 0, strlen('styles')) === 'styles'))
            ) {
                unlink($filePath);
            }
        }
    }

    private static function createBackup($zipFileName, $rootPath) {
        // Initialize archive object
        $zip_file = $zipFileName;
        $zip = new ZipArchive();
        $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $rootFiles = scandir($rootPath);
        $exclusionFiles = [
            ".", "..", "vendor", "assets", "runtime", ".git"
        ];

        foreach ($rootFiles as $rootFile) {
            if (in_array($rootFile, $exclusionFiles)) {
                continue;
            }

            $filePath = "$rootPath/$rootFile";
            $backslashPos = strpos(strrev($filePath), '\\');
            $basePath = strrev(substr(strrev($filePath), $backslashPos + 1));
            if (is_file($filePath)) {
                $zip->addFile($basePath, $rootFile);
            }

            if (is_dir($filePath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($filePath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        // Get real and relative path for current file
                        $realPath = $file->getRealPath();
                        $relativePath = substr($realPath, strlen($rootPath) + 1);
                        $zip->addFile($realPath, $relativePath);
                    }
                }
            }
        }
    }

    private static function restoreBackup($zipFileName, $basePath) {
        if (!empty($zipFileName) && file_exists($zipFileName)) {
            $filePath = $zipFileName;
            $zip = new ZipArchive;
            $res = $zip->open($filePath);
            if ($res === true) {
                $zip->extractTo($basePath);
                $zip->close();
            }
        }
    }

    private static function deleteBackup($zipFileName) {
        if (!empty($zipFileName) && file_exists($zipFileName)) {
            unlink($zipFileName);
        }
    }

    public function downloadUpdate() {
        try {
            $downloadDirectory = self::getDownloadDirectory();
            if (!file_exists($downloadDirectory)) {
                mkdir($downloadDirectory);
            }

            $latestVersion = PosVersion::getLatestPosData();
            if ($latestVersion) {
                if (!self::processFile($latestVersion, $this->startUpdate, null, 'pos')) {
                    return false;
                }
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public function downloadUpdateKiosk() {
        try {
            $downloadDirectory = self::getDownloadDirectoryKiosk($this->pathFolder);
            if (!file_exists($downloadDirectory)) {
                mkdir($downloadDirectory);
            }

            $latestVersion = $this->latestVersion;
            if ($latestVersion) {
                if (!self::processFile($latestVersion, $this->startUpdate, $this->pathFolder, 'kiosk')) {
                    throw new Exception("Failed download process files");
                }

                return true;
            }

            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function downloadUpdateOds() {
        try {

            $downloadDirectory = self::getDownloadDirectoryOds($this->pathFolder);
            if (!file_exists($downloadDirectory)) {
                mkdir($downloadDirectory);
            }

            $latestVersion = $this->latestVersion;
            if ($latestVersion) {
                if (!self::processFile($latestVersion, $this->startUpdate, $this->pathFolder, 'ods')) {
                    throw new Exception("Failed download process files");
                }

                return true;
            }

            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    private static function processFile($version, $startUpdate, $pathFolder, $processType, $attempt = 0) {
        try {
            $md5 = $version["downloadMd5"];
            $downloadUrl = $version['downloadUrl'];
            $versionName = $version['name'];

            $downloadDirectory = self::getDownloadDirectory();
            if ($processType == 'kiosk') {
                $downloadDirectory = self::getDownloadDirectoryKiosk($pathFolder);
            }

            if ($processType == 'ods') {
                $downloadDirectory = self::getDownloadDirectoryOds($pathFolder);
            }
            
            $versionDirectory = $downloadDirectory . DIRECTORY_SEPARATOR . $versionName;

            if (!file_exists($versionDirectory)) {
                mkdir($versionDirectory);
            }

            $fileName = basename($downloadUrl);
            $fileSavePath = $downloadDirectory . DIRECTORY_SEPARATOR . $versionName . DIRECTORY_SEPARATOR . $fileName;
            if (!file_exists($fileSavePath)) {
                self::downloadFile($downloadUrl, $fileSavePath, $versionName, $startUpdate);
            }

            if (strtolower(md5_file($fileSavePath)) == strtolower($md5)) {
                return true;
            } else {
                if (file_exists($fileSavePath)) {
                    self::removeFileDownloaded($fileSavePath);
                }

                if (file_exists($versionDirectory)) {
                    rmdir($versionDirectory);
                }

                $attempt += 1;
                if ($attempt > 5) {
                    return false;
                } else {
                    return self::processFile($version, $startUpdate, $pathFolder, $processType, $attempt);
                }
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    private static function downloadFile($url, $path, $latestVersionName, $startUpdate) {
        try {
            $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setOptions([
                    CURLOPT_FRESH_CONNECT => TRUE,
                    CURLOPT_CONNECTTIMEOUT => 600,
                    CURLOPT_TIMEOUT => 600
                ])
                ->send();
            
            if ($response->getIsOk()) {
                file_put_contents($path, $response->getContent());
                Yii::error('Download successfully ' . $path);
            } else {
                $errorMessage = $response->getContent();
                Yii::error('Failed download file ' . $errorMessage);
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            $exceptionMessage = $ex->getMessage();
    
            if (strpos($exceptionMessage, 'fopen(): php_network_getaddresses') !== false) {
                $exceptionMessage = 'No internet connection';
            } else if (strpos($exceptionMessage, 'timed out') !== false) {
                $exceptionMessage = 'The operation exceeded the maximum execution time.';
            }

            $currentVersion = PosVersion::getAppVersion();
            $currentVersionName = str_replace(array("\n", "\r"), '', $currentVersion["name"]);
            if ($exceptionMessage == $exceptionMessage) {
                self::setLoggingFailed($currentVersionName, $latestVersionName, $exceptionMessage, $startUpdate, true);
            } else {
                self::setLoggingFailed($currentVersionName, $latestVersionName, $exceptionMessage, $startUpdate, true);
            }

            self::removeFileDownloaded($path);
        }
    }


    private static function deleteUnusedFiles($rootPath, $action = 0) {
        $rootFiles = scandir($rootPath);
        foreach ($rootFiles as $rootFile) {
            $filePath = $rootPath . DIRECTORY_SEPARATOR . $rootFile;
            // 0 = rename, 1 = delete, 2 = restore
            if ($action === 0) {
                if (is_file($filePath) &&
                    ((substr($rootFile, 0, strlen('main')) === 'main') ||
                    (substr($rootFile, 0, strlen('polyfills')) === 'polyfills') ||
                    (substr($rootFile, 0, strlen('runtime')) === 'runtime') ||
                    (substr($rootFile, 0, strlen('scripts')) === 'scripts') ||
                    (substr($rootFile, 0, strlen('styles')) === 'styles'))
                ) {
                    rename($filePath, $filePath.'__DELETE');
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

    private static function setLoggingFailed($currentVersionName, $latestVersionName, $errorMessage, $startUpdate = null, $actionDownload = false) {
        $dataLogging = [
            'date' => [
                'startUpdate' => $startUpdate,
                'finishUpdate' => date('Y-m-d H:i:s')
            ],
            'beforeUpdate' => $currentVersionName,
            'afterUpdate' => $latestVersionName,
            'errorMessage' => $errorMessage
        ];

        if ($actionDownload) {
            Logging::save('-', Logging::FAIL_DOWNLOAD_POS_UPDATE, $dataLogging);
        } else {
            Logging::save('-', Logging::FAIL_UPDATE_POS_VERSION, $dataLogging);
        }
    }

    private static function removeFileDownloaded($path) {
        $dirs = explode(DIRECTORY_SEPARATOR, $path);
        array_pop($dirs);
        $newDir = implode('/', $dirs);
        $zipFiles = glob($newDir. '/*.zip');

        if (!empty($zipFiles)) {
            $totalZipFile = count($zipFiles);
            for ($i= 0; $i < $totalZipFile; $i++) { 
                unlink($zipFiles[$i]);
            }
        }
    }
}
