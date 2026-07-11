<?php


/* @var $this \yii\web\View */
/* @var $model TrGoodsDeliveryHead */
ob_start();
ob_implicit_flush(false);
?>
<html>
    <head>

    </head>
    <body>
        <div style="font-size: 12px">
            <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                <b>PEPPER LUNCH</b><br>
                <b>Boga Culinary Sdn Bhd</b><br>
                <b>1310444-V</b><br>
                <b>Lot No.L2-33 & 33A, Level 2</b><br>
                <b>Terminal KLIA 2</b><br>
                <b>64000 Sepang, Selangor D.E.</b><br>
                <b>Tel : +0603-8787 8883</b><br>
                <b>Daily Closing Report</b><br>
                <b>Date : <?= $salesDateSelect ?></b> <br>
            </div>
            
            <div style="margin-top: 10px;">
                <div style="float: left; width: 33%; font-weight: bold;">
                    <b>Printed By : <?= $shiftLog['fullName'] ? $shiftLog['fullName'] : '' ?></b>
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <b><?= date('d/m/Y H:i:s') ?></b>
                </div>
            </div>
            
            <div class="revenue">
                <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                    <b>REVENUE</b>
                </div>
                
                <?php
                foreach ($modelRevenue as $data):
                ?>
                <div style="margin-top: 1px;">
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr>
                                    <td>Net Sales</td>
                                </tr>
                                <tr>
                                    <td>Service Charge</td>
                                </tr>
                                <tr>
                                    <td>Rounding</td>
                                </tr>
                                <tr>
                                    <td>Total Before SST</td>
                                </tr>
                                <tr>
                                    <td>SST</td>
                                </tr>
                                <tr>
                                    <td>Total Include SST</td>
                                </tr>
                                <tr>
                                    <td>Average S / Pax</td>
                                </tr>
                                <tr>
                                    <td>Average S / Bill</td>
                                </tr>
                                <tr>
                                    <td>Total Reprints</td>
                                </tr>
                                <tr>
                                    <td>MTD Total Before SST</td>
                                </tr>
                                <tr>
                                    <td>MTD Total Include SST</td>
                                </tr>
                                <tr>
                                    <td>MTD Average S / Pax</td>
                                </tr>
                                <tr>
                                    <td>MTD Average S / Bill</td>
                                </tr>
                            </table>
                            <hr style="margin-bottom: 5px;">
                                <label style="font-size: 12px;">TOTAL BILLS COUNT</label>
                            <hr style="margin-top: 5px;">

                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><?= $data['paxTotal'] ? $data['paxTotal'] : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['billTotal'] ? $data['billTotal'] : 0 ?></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><?= $data['mtdPaxTotal'] ? $data['mtdPaxTotal'] : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['mtdBillTotal'] ? $data['mtdBillTotal'] : 0 ?></td>
                                </tr>
                            </table>
                            <hr style="margin-bottom: 5px;">
                            <label style="font-size: 12px">&nbsp;</label>
                            <hr style="margin-top: 5px;">
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= $data['netSales'] ? number_format($data['netSales'], 2, '.', ',') : 0?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['otherTaxTotal'] ? number_format($data['otherTaxTotal'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['roundingTotal'] ? number_format($data['roundingTotal'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['beforeSst'] ? number_format($data['beforeSst'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['sst'] ? number_format($data['sst'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['includeSst'] ? number_format($data['includeSst'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['averagePax'] ? number_format($data['averagePax'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['averageBill'] ? number_format($data['averageBill'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['billingPrintCount'] ? $data['billingPrintCount'] : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['mtdBeforeSst'] ? number_format($data['mtdBeforeSst'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['mtdIncludeSst'] ? number_format($data['mtdIncludeSst'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['mtdAveragePax'] ? number_format($data['mtdAveragePax'], 2, '.', ',') : 0 ?></td>
                                </tr>
                                <tr>
                                    <td><?= $data['mtdAverageBill'] ? number_format($data['mtdAverageBill'], 2, '.', ',') : 0 ?></td>
                                </tr>
                            </table>
                            <hr style="margin-bottom: 5px;">
                                <label style="font-size: 12px;"><?= $data['billTotal'] ? number_format($data['billTotal'], 0, '.', ',') : 0 ?></label>
                            <hr style="margin-top: 5px;">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <br>
            
            <div class="tendered">
                <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                    <b>TENDERED</b>
                </div>
                
                <div style="margin-top: 1px;">
                     <?php
                        $summaryQty = 0;
                        $summaryPaymentAmount = 0;
                        foreach ($modelTendered as $data):
                        $summaryQty += $data['qty'];
                        $summaryPaymentAmount += $data['paymentAmount'];
                    ?>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr>
                                    <td><?= $data['paymentMethodName'] ?></td>
                                </tr>
                                
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= $data['qty'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= number_format($data['paymentAmount'], 2, '.', ',') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;">Total Collection</label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= $summaryQty ?></label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= number_format($summaryPaymentAmount, 2, '.', ',') ?></label>
                    <hr style="margin-top: 5px;">
                </div>
            </div>
            <br>
            
            <div class="salesDetail">
                <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                    <b>SALES DETAIL</b>
                </div>
                
                <div style="margin-top: 1px;">
                    <?php
                    $summaryTotalTable = 0;
                    $summaryNetSales = 0;
                    foreach ($modelSalesDetail as $data):
                        $summaryTotalTable += $data['totalTable'];
                        $summaryNetSales += $data['netSales'];
                    ?>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr>
                                    <td><?= $data['modeOrder'] ?></td>
                                </tr>
                                
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= $data['totalTable'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= number_format($data['netSales'], 2, '.', ',') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;">Total</label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= $summaryTotalTable ?></label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= number_format($summaryNetSales, 2, '.', ',') ?></label>
                    <hr style="margin-top: 5px;">
                </div>
            </div>
            <br>
            
            <div class="salesItem">
                <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                    <b>SALES ITEMS</b>
                </div>
                
                <div style="margin-top: 1px;">
                    <?php
                    $summaryTotalQty = 0;
                    $summaryNetSales = 0;
                    foreach ($modelSalesItem as $data):
                        $summaryTotalQty += $data['totalQty'];
                        $summaryNetSales += $data['netSales'];
                    ?>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr>
                                    <td><?= $data['menuCategoryDesc'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= $data['totalQty'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= number_format($data['netSales'], 2, '.', ',') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                     <?php endforeach; ?>
                </div>
               
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;">Total</label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= $summaryTotalQty ?></label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= number_format($summaryNetSales, 2, '.', ',') ?></label>
                    <hr style="margin-top: 5px;">
                </div>
            </div>
            <br>
            
            <div class="amendement">
                <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                    <b>AMENDEMENT</b>
                </div>
                <div style="margin-top: 1px;">
                    <?php
                    $summaryTotalQty = 0;
                    $summaryNetSales = 0;
                    foreach ($modelAmendement as $data):
                        $summaryTotalQty += $data['totalQty'];
                        $summaryNetSales += $data['netSales'];
                    ?>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr>
                                    <td><?= $data['amendementName'] ?></td>
                                </tr>
                                
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= $data['totalQty'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= number_format($data['netSales'], 2, '.', ',') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;">Total</label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= $summaryTotalQty ?></label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= number_format($summaryNetSales, 2, '.', ',') ?></label>
                    <hr style="margin-top: 5px;">
                </div>
            </div>
            <br>
            
            <div class="salesItem">
                <div style="margin-top: 10px; text-align: center; font-size: 12px;">
                    <b>DISCOUNT</b>
                </div>
                
                <div style="margin-top: 1px;">
                    <?php
                    $summaryTotalQty = 0;
                    $summaryDiscountTotal = 0;
                    foreach ($modelDiscount as $data):
                        $summaryTotalQty += $data['totalQty'];
                        $summaryDiscountTotal += $data['discountTotal'];
                    ?>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr>
                                    <td><?= $data['discountName'] ?></td>
                                </tr>
                                
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= $data['totalQty'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="float: left; width: 33%; font-weight: bold;">
                        <div style="display: block;">
                            <table style="width: 100%; font-size: 12px">
                                <tr>
                                    <td><?= number_format($data['discountTotal'], 2, '.', ',') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;">Total</label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= $summaryTotalQty ?></label>
                    <hr style="margin-top: 5px;">
                </div>
                <div style="float: left; width: 33%; font-weight: bold;">
                    <hr style="margin-bottom: 5px;">
                        <label style="font-size: 12px;"><?= number_format($summaryDiscountTotal, 2, '.', ',') ?></label>
                    <hr style="margin-top: 5px;">
                </div>
            </div>
        </div>
	</body>
</html>

<?php return ob_get_clean(); ?>