<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_tablesectionstation".
 * 
 * @property integer $ID
 * @property integer $branchID
 * @property integer $tableSectionID
 * @property integer $menuCategoryDetailID
 * @property integer $stationID
 */

class TableSectionStation extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_tablesectionstation';
    }
    
    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['ID', 'branchID', 'tableSectionID', 'menuCategoryDetailID', 'stationID'], 'required']
        ];
    }
    
    public function getTableSection() {
        return $this->hasOne(TableSection::class, 
        ['tableSectionID' => 'tableSectionID']);
    }
    
    public function getMenuCategoryDetail(){
        return $this->hasOne(MenuCategoryDetail::class,
            ['menuCategoryDetailID' => 'menuCategoryDetailID']);
    }

    public function getStation() {
        return $this->hasOne(Station::class, 
                ['stationID' => 'stationID']);
    }
}