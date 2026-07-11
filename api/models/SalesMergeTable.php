<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmergetable".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $tableID
 * @property string $syncDate
 * 
 * @property SalesHead $salesHead
 * @property Table $table
 */
class SalesMergeTable extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesmergetable';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID', 'tableID'], 'integer'],
            [['salesNum', 'tableID'], 'required'],
            [['syncDate'], 'safe'],
            [['salesNum'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'tableID' => 'Table ID',
            'syncDate' => 'Sync Date'
        ];
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public function getTable() {
        return $this->hasOne(Table::class, ['tableID' => 'tableID']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public static function getMergeTableNames($salesNum){
        $mergeTable = SalesMergeTable::find()
            ->with('table')
            ->where(['salesNum' => $salesNum])
            ->all();
        $str ='';
        if($mergeTable){
            foreach($mergeTable as $detail){
                $str .= ', '.$detail->table->tableName;
            }
        }
        return $str;
    }

}
