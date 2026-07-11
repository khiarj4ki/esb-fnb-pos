<?php
namespace app\models\forms;

use app\models\Station;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property array $station
 */
class StationSetting extends Model {
    public $station;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['station'], 'required']
        ];
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->station as $station) {
                if (isset($station['updated'])) {
                    $stationModel = Station::find()
                        ->andWhere(['stationID' => $station['stationID']])
                        ->one();
                    if ($stationModel) {
                        $stationModel->printerTypeID = $station['printerTypeID'];
                        $stationModel->printingModeID = $station['printingModeID'];
                        $stationModel->printerConnectionID = $station['printerConnectionID'];
                        $stationModel->printerName = $station['printerName'];
                        $stationModel->printerPort = $station['printerPort'];
                        $stationModel->characterPerLine = $station['characterPerLine'];
                        $stationModel->flagAutocut = $station['flagAutocut'];
                        if (!$stationModel->save()) {
                            throw new Exception('Failed to update station');
                        }
                    }
                }
            }

            Logging::save('-', Logging::EDIT_STATION, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('station', $ex->getMessage());
            return false;
        }
    }

}
