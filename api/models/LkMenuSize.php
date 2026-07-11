<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_menusize".
 *
 * @property int $menuSizeID
 * @property string $menuSizeName
 * @property int $width
 * @property int $height
 */
class LkMenuSize extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_menusize';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuSizeID', 'menuSizeName', 'width', 'height'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuSizeID' => 'Menu Size ID',
            'menuSizeName' => 'Menu Size Name',
            'width' => 'width',
            'height' => 'height',
        ];
    }

}
