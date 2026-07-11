<?php
namespace app\models\forms;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_status".
 *
 * @property string $syncType
 */
class SyncOptimize extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_sync';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['syncType'], 'required'],
            [['syncType', 'pushDateTime', 'pullDateTime'], 'safe'],
            [['syncType'], 'string', 'max' => 50],
        ];
    }

}
