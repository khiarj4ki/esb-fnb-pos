<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_posfilteraccess".
 *
 * @property string $posAccessID
 * @property string $filterAccessID
 * @property string $description
 * @property string $subNodes
 * @property string $action
 * @property int $orderID
 * 
 * @property PosAccessControl $accessControl
 */
class PosFilterAccess extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_posfilteraccess';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['posAccessID', 'filterAccessID', 'description', 'subNodes'], 'required'],
            [['orderID'], 'integer'],
            [['posAccessID', 'filterAccessID'], 'string', 'max' => 10],
            [['description'], 'string', 'max' => 50],
            [['subNodes'], 'string', 'max' => 500],
            [['action'], 'string'],
            [['posAccessID', 'filterAccessID'], 'unique', 'targetAttribute' => ['posAccessID', 'filterAccessID']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'posAccessID' => 'Pos Access ID',
            'filterAccessID' => 'Filter Access ID',
            'description' => 'Description',
            'subNodes' => 'Sub Nodes',
            'action' => 'Action',
            'orderID' => 'Order ID',
        ];
    }

    public function getAccessControl() {
        return $this->hasOne(PosAccessControl::class,
                ['posAccessID' => 'posAccessID']);
    }

}
