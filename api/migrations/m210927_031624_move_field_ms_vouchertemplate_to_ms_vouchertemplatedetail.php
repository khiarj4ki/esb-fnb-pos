<?php

use app\models\VoucherTemplate;
use app\models\VoucherTemplateDetail;
use yii\db\Migration;

/**
 * Class m210927_031624_move_field_ms_vouchertemplate_to_ms_vouchertemplatedetail
 */
class m210927_031624_move_field_ms_vouchertemplate_to_ms_vouchertemplatedetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        // Move data from MsVoucherTemplate to MsVoucherTemplateDetail
        $voucherTemplates = VoucherTemplate::find()->all();
        if (count($voucherTemplates) > 0) {
            foreach ($voucherTemplates as $voucherTemplate) {
                $this->insert(
                    VoucherTemplateDetail::tableName(),
                    [
                        'voucherTemplateID' => $voucherTemplate['voucherTemplateID'],
                        'minSalesPrice' => $voucherTemplate['minSalesPrice'],
                        'minSalesUsagePrice' => $voucherTemplate['minSalesUsagePrice'],
                        'maxVoucherAmount' => $voucherTemplate['maxVoucherAmount'],
                        'voucherAmount' => $voucherTemplate['voucherAmount'],
                        'voucherPercentage' => $voucherTemplate['voucherPercentage'],
                    ]
                );
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        echo 'm210927_031624_move_field_ms_vouchertemplate_to_ms_vouchertemplatedetail cannot be reverted.\n';

        return false;
    }
}
