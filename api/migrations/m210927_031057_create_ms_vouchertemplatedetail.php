<?php

use app\models\VoucherTemplate;
use app\models\VoucherTemplateDetail;
use yii\db\Migration;

/**
 * Class m210927_031057_create_ms_vouchertemplatedetail
 */
class m210927_031057_create_ms_vouchertemplatedetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(VoucherTemplateDetail::tableName(), true) === null) {
            $this->createTable(
                VoucherTemplateDetail::tableName(),
                [
                    'voucherTemplateDetailID' => $this->primaryKey(),
                    'voucherTemplateID' => $this->integer(11)->notNull(),
                    'minSalesPrice' => $this->decimal(20, 4)->notNull()->defaultValue(0),
                    'minSalesUsagePrice' => $this->decimal(20, 4)->notNull()->defaultValue(0),
                    'maxVoucherAmount' => $this->decimal(20, 4)->notNull()->defaultValue(0),
                    'voucherAmount' => $this->decimal(20, 4)->notNull()->defaultValue(0),
                    'voucherPercentage' => $this->decimal(20, 4)->notNull()->defaultValue(0),
                ]
            );

            $voucherTemplates = VoucherTemplate::find()->all();
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
        if ($this->db->getTableSchema(VoucherTemplateDetail::tableName(), true) !== null) {
            $this->dropTable(VoucherTemplateDetail::tableName());
        }
    }
}
