<?php

namespace app\models\forms;

use app\models\Setting;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use ZipArchive;

class UpdateOds extends Model
{
    public $odsVersion;
    public $odsBaseHref;
    public $responseMessage;
    
    public function rules()
    {
        return [
            [['odsVersion'], 'required'],
            [['odsBaseHref'], 'safe'],
        ];
    }

    public static function getMainDirectory($path)
    {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);
        $projectPath = join(DIRECTORY_SEPARATOR, $dirs);
        return $projectPath . $path;
    }

    public static function getMainDirectoryOds($path = null) {
        $dirs = explode(DIRECTORY_SEPARATOR, Yii::$app->basePath);
        array_pop($dirs);

        $projectPath = join(DIRECTORY_SEPARATOR, $dirs);
        $projectPath = str_replace('\esb-fnb-pos', '',$projectPath );

        return $projectPath . $path;
    }

    public static function getDownloadDirectory($path)
    {
        return self::getMainDirectoryOds($path) . DIRECTORY_SEPARATOR . 'update-esb-fnb-ods';
    }

    public function checkUpdate()
    {
        $branchID = Setting::getCurrentBranch();
        $apiVersion = 'esb_api';
        $odsVersion = $this->odsVersion;
        $apiKey = Setting::getApiKey();
        $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/update/check-update-ods';
        $client = new Client();
        $response = $client->post($apiUrl)
            ->addHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey
            ])
            ->addData([
                'branchID' => $branchID,
                'odsVersion' => $odsVersion
            ])->send();

        $versionID = NULL;
        $versionName = NULL;
        if ($response->getIsOk()) {
            $versionID = $response->getData()['id'];
            $versionName = $response->getData()['name'];
        } else {
            Yii::warning($response->getData());
            $message = 'Failed to fetch data';
            $this->responseMessage = [
                'message' => $message
            ];
            self::handleErrorAction();
            throw new Exception($message);
        }

        if (!empty($versionID) || !empty($versionName)) {
            return $response->getData();
        } else {
            return null;
        }
    }

    public function applyUpdate()
    {
        $updateSuccess = true;
        $mainFilePath = self::getMainDirectoryOds($this->odsBaseHref);
        try {
            $currentVersion = self::getCurrentVersion($this->odsVersion);
            $newVersion = $this->checkUpdate();
            if (!$newVersion) {
                $this->handleErrorAction();
                return false;
            }

            if (!$this->processFile($newVersion, 0, $this->odsBaseHref)) {
                $latestVersionName = $newVersion && $newVersion['name'] ? $newVersion['name'] : null;
                $this->handleErrorAction($latestVersionName);
                return false;
            }

            // @notes: Temporary Rename
            self::deleteUnusedFiles($mainFilePath, 0);

            $latestVersionName = $newVersion['name'];
            $downloadDirectory = self::getDownloadDirectory($this->odsBaseHref);
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

            if ($updateSuccess) {
                // @notes: Delete
                self::deleteUnusedFiles($mainFilePath, 1);

                if (isset($currentVersion) && $currentVersion !== '') {
                    $dataLogging = [
                        'beforeUpdate' => $currentVersion[1],
                        'afterUpdate' => $latestVersionName
                    ];
    
                    Logging::save('-', Logging::UPDATE_ODS_VERSION, $dataLogging);
                }

                $versionUpdated = $newVersion['id'] . "|" . $newVersion['name'];
                self::setOdsVersion($versionUpdated);
            } else {
                if (isset($currentVersion) && $currentVersion !== '') {
                    $responseMessage = 'Failed to open file';
                    self::setLoggingFailUpdate($currentVersion, $latestVersionName, $responseMessage);
                }
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

    public function downloadUpdateOdsVersion() {

        $mainFilePath = self::getMainDirectoryOds($this->odsBaseHref);
        try {
            
            $newVersion = $this->checkUpdate();
            if (!$newVersion) {
                $this->handleErrorAction();
                return false;
            }

            if (!$this->processFile($newVersion, 0, $this->odsBaseHref)) {
                $latestVersionName = $newVersion && $newVersion['name'] ? $newVersion['name'] : null;
                $this->handleErrorAction($latestVersionName);
                return false;
            }

            return true;
        } catch (Exception $ex) {
            // @notes Restore
            self::deleteUnusedFiles($mainFilePath, 2);
            return false;
        }
    }

    private static function deleteDirectory($dir)
    {
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

    public function processFile($version, $attempt = 0, $odsBaseHref)
    {
        $md5 = $version["downloadMd5"];
        $downloadUrl = $version['downloadUrl'];
        $versionName = $version['name'];
        $downloadDirectory = self::getDownloadDirectory($odsBaseHref);
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
                return self::processFile($version, $attempt, $odsBaseHref);
            }
        }
    }

    public function downloadFile($url, $path)
    {
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


    private static function deleteUnusedFiles($rootPath, $action = 0)
    {
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

    public static function setOdsVersion($latestVersion, $flagManualUpdate = false, $productType = null) {
        $key1 = 'Local Setting';
        $key2 = 'Ods Version';
        Setting::setNewVersion($key1, $key2, $latestVersion, $flagManualUpdate, $productType);
    }

    private static function getCurrentVersion($odsVersion) {
        return $odsVersion ? explode("|", $odsVersion) : '';
    }

    public function handleErrorAction($latestVersion = null) {
        $currentVersion = self::getCurrentVersion($this->odsVersion);
        $responseMessage = $this->responseMessage ? $this->responseMessage['message'] : '';
        self::setLoggingFailUpdate($currentVersion, $latestVersion, $responseMessage);
    }

    private function setLoggingFailUpdate($currentVersion, $latestVersionName, $responseMessage) {
        if (isset($currentVersion) && $currentVersion !== '') {
            $dataLogging = [
                'beforeUpdate' => $currentVersion[1],
                'afterUpdate' => $latestVersionName,
                'errorMessage' => $responseMessage ? $responseMessage : null
            ];

            Logging::save('-', Logging::FAIL_UPDATE_ODS_VERSION, $dataLogging);
        }
    }
}
