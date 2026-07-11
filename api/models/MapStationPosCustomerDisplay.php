<?php

namespace app\models;
use app\models\MsPosCustomerDisplayDetail;
use yii\helpers\Url;

/**
 * This is the model class for table "map_station_poscustomerdisplay".
 *
 * @property int $posCustomerDetailID
 * @property int $stationID
 */
class MapStationPosCustomerDisplay extends \yii\db\ActiveRecord {

    public static function tableName() {
        return 'map_stationposcustomerdisplay';
    }

    public function rules() {
        return [
            [['posCustomerDetailID','stationID'], 'required'],
            [['posCustomerDetailID','stationID'], 'integer'],
            [['posCustomerDetailID','stationID'], 'safe']
        ];
    }

    public function getCustomerDisplayDetail() {
        return $this->hasMany(MsPosCustomerDisplayDetail::class, 
            ['ID' => 'posCustomerDetailID']
        );
    }
    public function getPosCustomerDisplayApplication() {
        return $this->hasMany(PosCustomerDisplayApplication::class, 
            ['posCustomerDetailID' => 'posCustomerDetailID']
        );
    }

    public static function findCustomerDisplayByStation($stationID, $applicationID) {
        $dirCustomerDisplay = Url::to('@web/images/customer-display/', true);
        $query = self::find()
        ->innerJoinWith('customerDisplayDetail')
        ->innerJoinWith('posCustomerDisplayApplication')
        ->where(['applicationID' => $applicationID]);
        if ($stationID <> 0) {
            $query->andWhere(['stationID' => $stationID]);
        }
        $q = $query->all();
        $data = [];
        $i = 0;
        foreach ($q as $value) {
            foreach ($value->customerDisplayDetail as $img) {
                $data[$i] = [
                    "ID" => $img->ID,
                    "posCustomerDisplayID" => $img->posCustomerDisplayID,
                    "imageUrl" => $dirCustomerDisplay . '/' . $img->imageUrl
                ];
            }
            $i++;
        }
        return $data;
    }

}
