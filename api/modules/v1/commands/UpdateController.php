<?php

namespace app\modules\v1\commands;

use app\models\forms\SyncFetch;
use app\models\forms\UpdatePos;
use app\models\PosVersion;
use yii\console\Controller;

class UpdateController extends Controller {
    public function actionIndex() {
        $fetchPosModel = new SyncFetch([
            'attributes' => [
                'syncType' => SyncFetch::FETCH_POS_VERSION
            ]
        ]);

        if ($fetchPosModel->doSync()) {
            $versions = PosVersion::getPosVersions();
            $downloadDirectory = UpdatePos::getDownloadDirectory();
            if (!file_exists($downloadDirectory)) {
                mkdir($downloadDirectory);
            }
            if ($versions) {
                foreach ($versions as $version) {
                    if (!$this->processFile($version)) {
                        echo "Process failed 1";
                        die();
                    }
                }
                echo "Process success";
            } else {
                echo "Process failed 2";
            }
        } else {
            echo "Process failed 3";
        }
    }

    private function processFile($version, $attempt = 0) {
        $md5 = $version["downloadMd5"];
        $downloadUrl = $version['downloadUrl'];
        $versionName = $version['versionName'];
        $downloadDirectory = UpdatePos::getDownloadDirectory();

        if (!file_exists("$downloadDirectory/$versionName")) {
            mkdir("$downloadDirectory/$versionName");
        }

        $fileName = basename($downloadUrl);
        $fileSavePath = "$downloadDirectory/$versionName/$fileName";
        if (!file_exists($fileSavePath)) {
            $this->downloadFile($downloadUrl, $fileSavePath);
        }

        if (strtolower(md5_file($fileSavePath)) == strtolower($md5)) {
            return true;
        } else {
            if (file_exists($fileSavePath)) {
                unlink($fileSavePath);
            }
            if (file_exists("$downloadDirectory/$versionName")) {
                rmdir("$downloadDirectory/$versionName");
            }

            $attempt += 1;
            if ($attempt > 5) {
                return false;
            } else {
                return $this->processFile($version, $attempt);
            }
        }
    }

    private function downloadFile($url, $path) {
        $newfname = $path;
        $file = fopen($url, 'rb');
        if ($file) {
            $newf = fopen($newfname, 'wb');
            if ($newf) {
                while (!feof($file)) {
                    fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
                }
            }
        }
        if ($file) {
            fclose($file);
        }
        if ($newf) {
            fclose($newf);
        }
    }

}
