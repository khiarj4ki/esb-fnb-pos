<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m210627_081716_update_print_shift_local_settings
 */
class m210627_081716_update_print_shift_local_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $checkPrintShiftLocalSettings = Setting::find()
            ->where([
                'key1' => 'POS'
            ])
            ->andWhere([
                'IN', 'key2', [
                    'Print Cancelled Menu',
                    'Print Cancelled Menu Summary',
                    'Print Closing Notes',
                    'Print Custom Menu Sales',
                    'Print Deposit Detail',
                    'Print Deposit Summary',
                    'Print Non Sales Bill Summary',
                    'Print Non Sales By Menu',
                    'Print Non Sales Menu Summary',
                    'Print Non Sales Payment by Cashier',
                    'Print Non Sales Payment Method Detail',
                    'Print Non Sales Payment Method Summary',
                    'Print Payment by Cashier',
                    'Print Payment Method Detail',
                    'Print Payment Method Summary',
                    'Print Pending Sales',
                    'Print Promotion Summary',
                    'Print Sales by Menu Category',
                    'Print Sales by Menu Category Detail',
                    'Print Sales By Menu Group',
                    'Print Sales by Menu Qty',
                    'Print Sales by Menu Qty Value',
                    'Print Sales by Menu Value',
                    'Print Sales by Mode',
                    'Print Sales by Type',
                    'Print Sales By Visit Purpose',
                    'Print Sales Menu by Mode',
                    'Print Sales Menu Package',
                    'Print Sales Per Date',
                    'Print Sales per Menu Category',
                    'Print Sales Voucher Usage',
                    'Print Shift Sales by Menu Value',
                    'Print Shift Summary',
                    'Print Special Price Summary',
                    'Print Void Payment Detail',
                    'Print Void Payment Summary',
                    'Queue Number'
                ]
            ]);

        if (!$checkPrintShiftLocalSettings->exists()) {
            Setting::updateAll(
                [
                    'key1' => 'POS'
                ],
                [
                    'AND', ['key1' => 'Local Setting'], [
                        'IN', 'key2', [
                            'Print Cancelled Menu',
                            'Print Cancelled Menu Summary',
                            'Print Closing Notes',
                            'Print Custom Menu Sales',
                            'Print Deposit Detail',
                            'Print Deposit Summary',
                            'Print Non Sales Bill Summary',
                            'Print Non Sales By Menu',
                            'Print Non Sales Menu Summary',
                            'Print Non Sales Payment by Cashier',
                            'Print Non Sales Payment Method Detail',
                            'Print Non Sales Payment Method Summary',
                            'Print Payment by Cashier',
                            'Print Payment Method Detail',
                            'Print Payment Method Summary',
                            'Print Pending Sales',
                            'Print Promotion Summary',
                            'Print Sales by Menu Category',
                            'Print Sales by Menu Category Detail',
                            'Print Sales By Menu Group',
                            'Print Sales by Menu Qty',
                            'Print Sales by Menu Qty Value',
                            'Print Sales by Menu Value',
                            'Print Sales by Mode',
                            'Print Sales by Type',
                            'Print Sales By Visit Purpose',
                            'Print Sales Menu by Mode',
                            'Print Sales Menu Package',
                            'Print Sales Per Date',
                            'Print Sales per Menu Category',
                            'Print Sales Voucher Usage',
                            'Print Shift Sales by Menu Value',
                            'Print Shift Summary',
                            'Print Special Price Summary',
                            'Print Void Payment Detail',
                            'Print Void Payment Summary',
                            'Queue Number'
                        ]
                    ]
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkPrintShiftLocalSettings = Setting::find()
            ->where([
                'key1' => 'Local Setting'
            ])
            ->andWhere([
                'IN', 'key2', [
                    'Print Cancelled Menu',
                    'Print Cancelled Menu Summary',
                    'Print Closing Notes',
                    'Print Custom Menu Sales',
                    'Print Deposit Detail',
                    'Print Deposit Summary',
                    'Print Non Sales Bill Summary',
                    'Print Non Sales By Menu',
                    'Print Non Sales Menu Summary',
                    'Print Non Sales Payment by Cashier',
                    'Print Non Sales Payment Method Detail',
                    'Print Non Sales Payment Method Summary',
                    'Print Payment by Cashier',
                    'Print Payment Method Detail',
                    'Print Payment Method Summary',
                    'Print Pending Sales',
                    'Print Promotion Summary',
                    'Print Sales by Menu Category',
                    'Print Sales by Menu Category Detail',
                    'Print Sales By Menu Group',
                    'Print Sales by Menu Qty',
                    'Print Sales by Menu Qty Value',
                    'Print Sales by Menu Value',
                    'Print Sales by Mode',
                    'Print Sales by Type',
                    'Print Sales By Visit Purpose',
                    'Print Sales Menu by Mode',
                    'Print Sales Menu Package',
                    'Print Sales Per Date',
                    'Print Sales per Menu Category',
                    'Print Sales Voucher Usage',
                    'Print Shift Sales by Menu Value',
                    'Print Shift Summary',
                    'Print Special Price Summary',
                    'Print Void Payment Detail',
                    'Print Void Payment Summary',
                    'Queue Number'
                ]
            ]);

        if (!$checkPrintShiftLocalSettings->exists()) {
            Setting::updateAll(
                [
                    'key1' => 'Local Setting'
                ],
                [
                    'AND', ['key1' => 'POS'], [
                        'IN', 'key2', [
                            'Print Cancelled Menu',
                            'Print Cancelled Menu Summary',
                            'Print Closing Notes',
                            'Print Custom Menu Sales',
                            'Print Deposit Detail',
                            'Print Deposit Summary',
                            'Print Non Sales Bill Summary',
                            'Print Non Sales By Menu',
                            'Print Non Sales Menu Summary',
                            'Print Non Sales Payment by Cashier',
                            'Print Non Sales Payment Method Detail',
                            'Print Non Sales Payment Method Summary',
                            'Print Payment by Cashier',
                            'Print Payment Method Detail',
                            'Print Payment Method Summary',
                            'Print Pending Sales',
                            'Print Promotion Summary',
                            'Print Sales by Menu Category',
                            'Print Sales by Menu Category Detail',
                            'Print Sales By Menu Group',
                            'Print Sales by Menu Qty',
                            'Print Sales by Menu Qty Value',
                            'Print Sales by Menu Value',
                            'Print Sales by Mode',
                            'Print Sales by Type',
                            'Print Sales By Visit Purpose',
                            'Print Sales Menu by Mode',
                            'Print Sales Menu Package',
                            'Print Sales Per Date',
                            'Print Sales per Menu Category',
                            'Print Sales Voucher Usage',
                            'Print Shift Sales by Menu Value',
                            'Print Shift Summary',
                            'Print Special Price Summary',
                            'Print Void Payment Detail',
                            'Print Void Payment Summary',
                            'Queue Number'
                        ]
                    ]

                ]
            );
        }
    }
}
