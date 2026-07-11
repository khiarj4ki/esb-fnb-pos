<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m210223_053809_update_esb_order_qs_url
 */
class m210223_053809_update_esb_order_qs_url extends Migration {

    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'EZO TA API Url'])->exists()) {
            $this->insert(Setting::tableName(),
                    [
                        'key1' => 'Local Setting',
                        'key2' => 'EZO TA API Url',
                        'value1' => 'Mn3IbDLkmF8j4gLBsPQRxzkxNDQ5MTM3MzhjMzZhOTZkNmY4OTM1NjhkOTI5ZDg0MjAzYzU3YWM4NmYyNDkyNGFhZmYyODdkNTNiYTcxMzJg7xNUBx+kznxvnnaFnrq8z9mR7//0N2TElKiHZkgWsBZdnYhsFy5KvJw/x0NGYSZAWksJaTPJgVVVWzv4pgyw',
                        'value2' => 'Enc'
            ]);
        } else {
            Setting::updateAll(['value1' => 'Mn3IbDLkmF8j4gLBsPQRxzkxNDQ5MTM3MzhjMzZhOTZkNmY4OTM1NjhkOTI5ZDg0MjAzYzU3YWM4NmYyNDkyNGFhZmYyODdkNTNiYTcxMzJg7xNUBx+kznxvnnaFnrq8z9mR7//0N2TElKiHZkgWsBZdnYhsFy5KvJw/x0NGYSZAWksJaTPJgVVVWzv4pgyw'],
                    ['key1' => 'Local Setting', 'key2' => 'EZO TA API Url']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
