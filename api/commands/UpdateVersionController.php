<?php

namespace app\commands;

use app\models\forms\SyncFetch;
use yii\console\Controller;

class UpdateVersionController extends Controller {
    public function actionIndex() {
        $fetchPosModel = new SyncFetch([
            'attributes' => [
                'syncType' => SyncFetch::FETCH_POS_VERSION
            ]
        ]);

        if ($fetchPosModel->doSync()) {
            echo "Successfully sync version data";
        } else {
            echo "Failed to sync version data";
        }
    }

}
