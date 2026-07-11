<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_posaccesscontrol".
 *
 * @property string $posAccessID
 * @property string $description
 * @property string $node
 * @property string $icon
 * @property int $orderID
 * 
 * @property PosFilterAccess[] $filterAccess
 */
class PosAccessControl extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_posaccesscontrol';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['posAccessID', 'description', 'node', 'icon'], 'required'],
            [['orderID'], 'integer'],
            [['posAccessID'], 'string', 'max' => 10],
            [['description', 'node', 'icon'], 'string', 'max' => 50],
            [['posAccessID'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'posAccessID' => 'Pos Access ID',
            'description' => 'Description',
            'node' => 'Node',
            'icon' => 'Icon',
            'orderID' => 'Order ID',
        ];
    }

    public function getFilterAccess() {
        return $this->hasMany(PosFilterAccess::class,
                ['posAccessID' => 'posAccessID']);
    }

}
