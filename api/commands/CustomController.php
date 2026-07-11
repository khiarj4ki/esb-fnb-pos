<?php
namespace app\commands;

use Yii;
use app\components\AppHelper;
use yii\console\Controller;
use Mpdf\Mpdf;

class CustomController extends Controller {
    public function actionIoiCsv($salesDateStr = '') {
        $connection = Yii::$app->getDb();

        $salesDateStr = $salesDateStr == '' ? date('Y-m-d') : $salesDateStr;
        $salesDateSelect = date('dmY', strtotime($salesDateStr));
        $manchineID = Yii::$app->params['manchineID'];

        $command = $connection->createCommand("SELECT
            'H$manchineID' as machineID,
            '1' as batchID,
            '$salesDateSelect' as salesDate,
            a.hh 'hh',
            COALESCE(g.receiptTotal, 0) 'receiptTotal',
            CAST(COALESCE(g.GTOSales, 0) AS DECIMAL(18,2)) 'GTOSales',
            CAST(COALESCE(g.SST, 0) AS DECIMAL(18,2)) 'SST',
            CAST(COALESCE(g.discountTotal, 0) AS DECIMAL(18,2)) 'discountTotal',
            CAST(COALESCE(g.otherTaxTotal, 0) AS DECIMAL(18,2)) 'otherTaxTotal',
            CAST(COALESCE(g.paxTotal, 0) AS DECIMAL(18,0)) 'paxTotal',
            CAST(COALESCE(b.Amount, 0) AS DECIMAL(18,2)) 'Cash',
            CAST(COALESCE(h.Amount, 0) AS DECIMAL(18,2)) 'TNG',
            CAST(COALESCE(d.Amount, 0) AS DECIMAL(18,2)) 'Visa',
            CAST(COALESCE(c.Amount, 0) AS DECIMAL(18,2)) 'MasterCard',
            CAST(COALESCE(e.Amount, 0) AS DECIMAL(18,2)) 'Amex',
            CAST(COALESCE(f.Amount, 0) AS DECIMAL(18,2)) 'Voucher',
            CAST(COALESCE(i.Amount, 0) AS DECIMAL(18,2)) 'Others',
            'Y'  AS 'SSTRegsitered'
            FROM (
            SELECT @N := LPAD(@N +1,2,0) AS hh
            FROM INFORMATION_SCHEMA.COLUMNS, (SELECT @N:=-1) dummyRowNums LIMIT 24
            ) a
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh',SUM(a.paymentAmount - c.paymentTotal + c.grandTotal - c.roundingTotal) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 1
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                GROUP BY HOUR(salesDateOut)
            ) b ON b.hh = a.hh
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh',SUM(paymentAmount) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID <> 7
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                AND b.paymentMethodName like '%Master%'
                GROUP BY HOUR(salesDateOut)
            ) c ON c.hh = a.hh
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh',SUM(paymentAmount) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID <> 7
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                AND b.paymentMethodName like '%Visa%'
                GROUP BY HOUR(salesDateOut)
            ) d ON d.hh = a.hh
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh',SUM(paymentAmount) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID <> 7
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                AND b.paymentMethodName like '%Amex%'
                GROUP BY HOUR(salesDateOut)
            ) e ON e.hh = a.hh
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh',SUM(paymentAmount) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID <> 7
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                AND b.paymentMethodTypeID IN (4,5)
                GROUP BY HOUR(salesDateOut)
            ) f ON f.hh = a.hh
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh', 
                COUNT(salesNum) 'receiptTotal',
                SUM(subTotal) 'subTotal',
                SUM(subTotal) 'TNG',
                SUM(subtotal - menuDiscountTotal - discountTotal + otherTaxTotal) 'GTOSales',
                SUM(vatTotal) 'SST',
                SUM(discountTotal + menuDiscountTotal) 'discountTotal',
                SUM(otherTaxTotal) 'otherTaxTotal',
                SUM(paxTotal) 'paxTotal'
                FROM tr_saleshead c 
                WHERE salesDate = '$salesDateStr' AND c.statusID = 8
                AND c.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
                GROUP BY HOUR(salesDateOut)
            ) g ON g.hh = a.hh 
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh', SUM(paymentAmount) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID <> 7
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                AND b.paymentMethodName like '%Touch N Go%'
                GROUP BY HOUR(salesDateOut)
            ) h ON h.hh = a.hh
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh', SUM(paymentAmount) 'Amount' 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID 
                AND b.paymentMethodTypeID NOT IN (1,4,5,7) 
                AND b.paymentMethodName not like '%Master%' AND b.paymentMethodName not like '%Visa%'
                AND b.paymentMethodName not like '%Amex%' AND b.paymentMethodName not like '%Touch N Go%'
                JOIN tr_saleshead c ON c.salesNum = a.salesNum AND c.statusID = 8
                WHERE salesDate = '$salesDateStr'
                GROUP BY HOUR(salesDateOut)
            ) i ON i.hh = a.hh
            ORDER BY a.hh;");

        $data = $command->queryAll();

        $coldefs = array(
            'machineID' => array(''),
            'batchID' => array(''),
            'salesDate' => array(''),
            'hh' => array(''),
            'receiptTotal' => array(''),
            'GTOSales' => array(''),
            'SST' => array(''),
            'discountTotal' => array(''),
            'otherTaxTotal' => array(''),
            'paxTotal' => array(''),
            'Cash' => array(''),
            'TNG' => array(''),
            'Visa' => array(''),
            'MasterCard' => array(''),
            'Amex' => array(''),
            'Voucher' => array(''),
            'Others' => array(''),
            'SSTRegsitered' => array('')
        );

        $path = Yii::$app->params['pathIoi'];
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $documentName = "$path\H$manchineID" . "_" . date('Ymd',
                strtotime($salesDateStr)) . ".txt";
        $this->GenerateCSV($data, $coldefs, "$documentName", '|', false);

        // try{
        //     $filename1 = \Yii::$app->basePath.'/mail/attachment/ioi.csv';
        //     Yii::$app->mailer->compose()
        //     ->setFrom(Yii::$app->params['esbSenderEmail'])
        //     ->setTo($emailRecipients)
        //     ->setSubject("Daily ESB - Sales Reporting IOI ". date('d-m-Y',strtotime($salesDateStr)))
        //     ->attach($filename1)
        //     //->attach($filename2)
        // 	->setHtmlBody("Dear All,<br><br><p>This is auto generated sales reporting email please see the attachment.</p><br><br>Thank You,<br><br>ESB Team.")
        //     ->send();
        // } catch (Exception $ex) {
        //     echo $ex;
        // }
    }

    public function actionParkwayParade($salesDateStr = '')
    {
        $connection = Yii::$app->getDb();
        $salesDateStr = $salesDateStr == '' ? date('Y-m-d') : $salesDateStr;
        $salesDateSelect = date('dmY', strtotime($salesDateStr));
        $machineID = Yii::$app->params['manchineID'];
        
        $shiftLogModel = $connection->createCommand("
            SELECT
                shiftID
                FROM tr_shiftlog
                WHERE CAST(shiftInTime as DATE) = '$salesDateStr'
                LIMIT 1")->queryOne();

        $shiftID = $shiftLogModel['shiftID'];

        $command = $connection->createCommand("SELECT
            '$machineID' as machineID,
            '$salesDateSelect' as salesDate,
            '$shiftID' as batchID,
            CONCAT(a.hh,'59') 'hour',
            COALESCE(b.receiptTotal, 0) 'receiptTotal',
            CAST(COALESCE(b.GTOSales, 0) AS DECIMAL(18,2)) 'GTOSales',
            CAST(COALESCE(b.GST, 0) AS DECIMAL(18,2)) 'GST',
            CAST(COALESCE(b.discountTotal, 0) AS DECIMAL(18,2)) 'discountTotal',
            CAST(COALESCE(b.paxTotal, 0) AS DECIMAL(18,0)) 'paxTotal'
            FROM (
            SELECT @N := LPAD(@N +1,2,0) AS hh
            FROM INFORMATION_SCHEMA.COLUMNS, (SELECT @N:=-1) dummyRowNums LIMIT 24
            ) a
            LEFT JOIN 
            (
                SELECT HOUR(salesDateOut) 'hh', 
                COUNT(salesNum) 'receiptTotal',
                SUM(subtotal - menuDiscountTotal - discountTotal + otherTaxTotal) 'GTOSales',
                SUM(vatTotal) 'GST',
                SUM(discountTotal + menuDiscountTotal) 'discountTotal',
                SUM(paxTotal) 'paxTotal'
                FROM tr_saleshead c 
                WHERE salesDate = '$salesDateStr' AND c.statusID = 8
                AND c.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
                GROUP BY HOUR(salesDateOut)
            ) b ON b.hh = a.hh
            ORDER BY a.hh;");

        $data = $command->queryAll();

        $coldefs = array(
            'machineID' => array(''),
            'salesDate' => array(''),
            'batchID' => array(''),
            'hour' => array(''),
            'receiptTotal' => array(''),
            'GTOSales' => array(''),
            'GST' => array(''),
            'discountTotal' => array(''),
            'paxTotal' => array('')
        );

        $path = Yii::$app->params['pathIoi'];
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $documentName = "$path\\$machineID" . "_" . date('Ymd') . "_" .date("His") . ".txt";
        $this->GenerateCSV($data, $coldefs, "$documentName", '|', false);
        
    }

    public function actionKliaTxt($salesDateStr = '') {
        $connection = Yii::$app->getDb();

        $salesDateStr = $salesDateStr == '' ? date('Y-m-d') : $salesDateStr;
        $salesDateSelect = date('dmY', strtotime($salesDateStr));
        $locationCode = Yii::$app->params['locationCode'];

        $queryShiftLog = $connection->createCommand("
            SELECT 
                ms_posuser.fullName,
                tr_shiftlog.shiftInTime,
                tr_shiftlog.shiftOutTime
                FROM tr_shiftlog 
                LEFT JOIN ms_posuser ON tr_shiftlog.shiftOutUsername = ms_posuser.username
                WHERE CAST(shiftOutTime AS DATE) = '$salesDateStr'
                AND tr_shiftlog.shiftOutTime IS NOT NULL
                ORDER BY tr_shiftlog.shiftOutTime DESC")->queryAll();


        $i= 0;
        foreach($queryShiftLog as $shiftLog) {
            $seq = AppHelper::toAlphabet($i);
            $shiftInTime = $shiftLog['shiftInTime'];
            $shiftOutTime = $shiftLog['shiftOutTime'];

            $command = $connection->createCommand("
            SELECT
                '$locationCode' as locationCode,
                '$salesDateSelect' as salesDate,
                ROUND(COALESCE(SUM(subtotal - (discountTotal + menuDiscountTotal) + otherTaxTotal), 0), 2) 'total'
            FROM tr_saleshead
            WHERE salesDate = '$salesDateStr' AND (salesDateIn BETWEEN '$shiftInTime' AND '$shiftOutTime' )AND statusID = 8 
            AND salesNum NOT IN (
                SELECT a.salesNum 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
            )");

            $data = $command->queryAll();
            
            $coldefs = array(
                'locationCode' => array(''),
                'salesDate' => array(''),
                'total' => array('')
            );

            $path = Yii::$app->params['pathKlia'];
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
                mkdir($path . '\Sent', 0777, true);
            }

            $documentName = "$path\\$locationCode" . "_" . date('dmY',
                    strtotime($salesDateStr)) . "_". $seq . ".txt";
            $this->GenerateCSV($data, $coldefs, "$documentName", ',', false);
            $i += 1;
        }

        
    }

    private function GenerateCSV($data, $coldefs, $fileLocation, $separator = ',', $header = true) {
        $endLine = "\r\n";
        $returnVal = '';
        $names = '';

        if ($header == true) {
            foreach ($coldefs as $col => $config) {
                $names .= '"' . utf8_decode($col) . '"' . $separator;
            }
            $names = rtrim($names, $separator);
            $returnVal .= $names . $endLine;
        }

        foreach ($data as $row) {
            $r = "";
            foreach ($coldefs as $col => $config) {
                if (isset($row[$col])) {
                    $val = $row[$col];
                    foreach ($config as $conf) {
                        $r .= '' . $val . '' . $separator;
                    }
                }
            }
            $item = trim(rtrim($r, $separator)) . $endLine;
            $returnVal .= $item;
        }

        $returnVal = rtrim($returnVal, "\r\n");

        $my_file = $fileLocation;
        $handle = fopen($my_file, 'a') or die('Cannot open file:  ' . $my_file);
        $fh = fopen($my_file, 'w'); // clear file
        $data = $returnVal;
        fwrite($handle, $data);
        fclose($handle);
    }

    public function actionKliaPdf($salesDateStr = '') {
        $connection = Yii::$app->getDb();

        $salesDateStr = $salesDateStr == '' ? date('Y-m-d') : $salesDateStr;
        $salesDateSelect = date('d/m/Y', strtotime($salesDateStr));
        $locationCode = Yii::$app->params['locationCode'];

        $startDate = date('Y-m', strtotime($salesDateStr)) . '-01';
        $lastDate = date('Y-m-t', strtotime($salesDateStr));

        $queryShiftLog = $connection->createCommand("
            SELECT 
                ms_posuser.fullName
                FROM tr_shiftlog LEFT JOIN ms_posuser ON tr_shiftlog.shiftOutUsername = ms_posuser.username
                WHERE CAST(shiftOutTime AS DATE) = '$salesDateStr'
                AND tr_shiftlog.shiftOutTime IS NOT NULL
                ORDER BY tr_shiftlog.shiftOutTime DESC
                LIMIT 1");

        $shiftLog = $queryShiftLog->queryOne();

        $queryRevenue = $connection->createCommand("
            SELECT 
                date_format(salesDate, '%Y%m%d') as salesDateOut,
                SUM(subtotal - (discountTotal + menuDiscountTotal)) as netSales,
                SUM(otherTaxTotal) as otherTaxTotal,
                SUM(roundingTotal) as roundingTotal,
                SUM(subtotal  - (discountTotal + menuDiscountTotal) + otherTaxTotal) as beforeSst,
                SUM(vatTotal) as sst,
                SUM(subtotal + otherTaxTotal + vatTotal - (discountTotal + menuDiscountTotal)) as includeSst,
                SUM(paxTotal) as paxTotal,
                COUNT(billNum) as billTotal,
                SUM(subtotal  - (discountTotal + menuDiscountTotal)) / paxTotal as averagePax,
                SUM(subtotal  - (discountTotal + menuDiscountTotal)) / COUNT(billNum) as averageBill,
                SUM(billingPrintCount) as billingPrintCount,
                (SELECT SUM(paxTotal) FROM tr_saleshead where salesDate between '$startDate' and '$lastDate') as mtdPaxTotal,
                (SELECT COUNT(billNum) FROM tr_saleshead where salesDate between '$startDate' and '$lastDate') as mtdBillTotal,
                (SELECT SUM((subtotal  - (discountTotal + menuDiscountTotal)) + otherTaxTotal) FROM tr_saleshead where salesDate between '$startDate' and '$lastDate') as mtdBeforeSst,
                (SELECT SUM((subtotal  - (discountTotal + menuDiscountTotal)) + otherTaxTotal + vatTotal  - (discountTotal + menuDiscountTotal)) FROM tr_saleshead where salesDate between '$startDate' and '$lastDate') as mtdIncludeSst,
                (SELECT SUM((subtotal  - (discountTotal + menuDiscountTotal)) / paxTotal) FROM tr_saleshead where salesDate between '$startDate' and '$lastDate') as mtdAveragePax,
                (SELECT SUM((subtotal  - (discountTotal + menuDiscountTotal))) / COUNT(billNum) FROM tr_saleshead where salesDate between '$startDate' and '$lastDate') as mtdAverageBill
                FROM tr_saleshead
                WHERE salesDate = '$salesDateStr'
                AND statusID = 8
                AND tr_saleshead.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
            GROUP BY date_format(salesDate, '%Y%m%d')");

        $modelRevenue = $queryRevenue->queryAll();

        $queryTendered = $connection->createCommand("
            SELECT 
                tr_salespayment.paymentMethodID,
                ms_paymentmethod.paymentMethodName,
                COUNT(tr_salespayment.salesNum) qty,
                SUM(CASE WHEN ms_paymentmethod.paymentMethodTypeID = 1 THEN (tr_salespayment.paymentAmount - (tr_saleshead.paymentTotal + tr_saleshead.roundingTotal - tr_saleshead.grandTotal)) ELSE tr_salespayment.paymentAmount END) AS paymentAmount
            FROM tr_saleshead
            LEFT JOIN tr_salespayment ON tr_saleshead.salesNum = tr_salespayment.salesNum
            JOIN ms_paymentmethod ON tr_salespayment.paymentMethodID = ms_paymentmethod.paymentMethodID AND ms_paymentmethod.paymentMethodTypeID <> 7
            WHERE salesDate = '$salesDateStr'
                AND statusID = 8
            GROUP BY tr_salespayment.paymentMethodID, ms_paymentmethod.paymentMethodName");

        $modelTendered = $queryTendered->queryAll();

        $querySalesDetail = $connection->createCommand("
            SELECT 
                'Eat In' as modeOrder, COUNT(tableID) as totalTable, 
                SUM((subtotal  - (discountTotal + menuDiscountTotal))) as netSales 
            FROM tr_saleshead
                WHERE tableID = 0
                AND tr_saleshead.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
                AND salesDate = '$salesDateStr'
                AND statusID = 8
            UNION
            SELECT 'Take Away' as modeOrder, COUNT(tableID) as totalTable, 
                SUM((subtotal  - (discountTotal + menuDiscountTotal))) as netSales 
            FROM tr_saleshead
                WHERE tableID > 0
                AND tr_saleshead.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
                AND salesDate = '$salesDateStr'
                AND statusID = 8");

        $modelSalesDetail = $querySalesDetail->queryAll();

        $querySalesItem = $connection->createCommand("
                SELECT d.menuCategoryDesc, SUM(b.Counter) 'totalQty' ,SUM(b.totalSales) 'netSales'
                FROM (
                SELECT
                d.menuCategoryDetailID,
                COUNT(d.menuCategoryDetailID) 'Counter',
                SUM(b.qty * COALESCE(c.qty,1) * b.price) as 'totalSales'
                FROM tr_saleshead a
                JOIN tr_salesmenu b on a.salesNum = b.salesNum and b.statusID = 13
                LEFT JOIN tr_salesmenu c on b.menuRefID = c.localID AND b.salesNum = c.salesNum AND b.menuGroupID > 0
                LEFT JOIN ms_menu d ON d.menuID = b.menuID
                WHERE a.salesDate = '$salesDateStr' AND a.statusID = 8
                AND a.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
                GROUP BY d.menuCategoryDetailID
                UNION ALL
                SELECT 
                d.menuCategoryDetailID,
                COUNT(d.menuCategoryDetailID) 'Counter',
                SUM(a.qty * b.price) FROM tr_salesmenu a
                JOIN tr_salesmenuextra b ON a.ID = b.menuDetailID
                JOIN tr_saleshead c ON c.salesNum = b.salesNum AND c.statusID = 8
                LEFT JOIN ms_menu d ON d.menuID = a.menuID
                WHERE c.salesDate = '$salesDateStr' AND a.statusID = 13
                AND c.salesNum NOT IN (
                    SELECT a.salesNum 
                    FROM tr_salespayment a
                    JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                    JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                )
                GROUP BY d.menuCategoryDetailID
                ) b 
                JOIN ms_menucategorydetail c ON c.ID = b.menuCategoryDetailID
                JOIN ms_menucategory d ON d.menuCategoryID = c.menuCategoryID
                GROUP BY d.menuCategoryDesc
                UNION
                SELECT 'Less : Disc' as menuCategoryDesc, 
                    COALESCE(COUNT(tr_saleshead.salesNum),0) as totalQty,
                    COALESCE(SUM(tr_saleshead.discountTotal + tr_saleshead.menuDiscountTotal) * -1,0) as netSale
                    
                FROM tr_saleshead
                WHERE statusID = 8
                    AND tr_saleshead.salesNum NOT IN (
                        SELECT a.salesNum 
                        FROM tr_salespayment a
                        JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                        JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
                    )
                    AND (tr_saleshead.discountTotal > 0 OR tr_saleshead.menuDiscountTotal > 0)
                    AND salesDate = '$salesDateStr'
            ");

        $modelSalesItem = $querySalesItem->queryAll();

        $querydAmendement = $connection->createCommand("
            SELECT 'REFUND' as amendementName,
                0 as totalQty,
                0 as netSales
            UNION
            SELECT 'CANCEL' as amendementName, 
                COUNT(salesNum) totalQty, 
                SUM((tr_saleshead.subtotal  - (tr_saleshead.discountTotal + tr_saleshead.menuDiscountTotal))) as netSales 
                FROM tr_saleshead
                WHERE tr_saleshead.statusID = 12
                AND salesDate = '$salesDateStr'
            UNION
            SELECT 'ROUNDING' as amendementName, 
                COUNT(salesNum) totalQty, 
                SUM(tr_saleshead.roundingTotal) as netSales 
            FROM tr_saleshead
                WHERE tr_saleshead.statusID = 8
                AND salesDate = '$salesDateStr'
            UNION
            SELECT 'VOID' as amendementName, 
                COUNT(salesNum) totalQty, 
                SUM((tr_saleshead.subtotal  - (tr_saleshead.discountTotal + tr_saleshead.menuDiscountTotal))) as netSales 
            FROM tr_saleshead
                WHERE tr_saleshead.statusID = 24
                AND salesDate = '$salesDateStr'
            UNION
            SELECT 'FOC' as amendementName, 
                COUNT(tr_saleshead.salesNum) totalQty, 
                COALESCE(SUM(tr_saleshead.subtotal  - (tr_saleshead.discountTotal + tr_saleshead.menuDiscountTotal)), 0) as netSales 
            FROM tr_saleshead
                LEFT JOIN tr_salespayment ON tr_saleshead.salesNum = tr_salespayment.salesNum
                LEFT JOIN ms_paymentmethod ON tr_salespayment.paymentMethodID = ms_paymentmethod.paymentMethodID
                WHERE tr_saleshead.statusID = 8
                AND ms_paymentmethod.paymentMethodTypeID = 7
                AND salesDate = '$salesDateStr'
            UNION
            SELECT 'FOOD RnD' as amendementName,
                0 as totalQty,
                0 as netSales");

        $modelAmendement = $querydAmendement->queryAll();

        $querydDiscount = $connection->createCommand("
            SELECT 
                ms_promotionhead.notes as discountName, 
                COUNT(ms_promotionhead.notes) as totalQty, 
                SUM(tr_saleshead.discountTotal) as discountTotal
            FROM tr_saleshead
            LEFT JOIN ms_promotionhead ON tr_saleshead.promotionID = ms_promotionhead.promotionID
            WHERE tr_saleshead.promotionID > 0
            AND tr_saleshead.statusID = 8
            AND salesDate = '$salesDateStr'
            AND tr_saleshead.salesNum NOT IN (
                SELECT a.salesNum 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
            )
            GROUP BY ms_promotionhead.notes
            UNION ALL
            SELECT 
            c.notes as discountName,
            COUNT(c.notes) as totalQty,
            SUM(a.discountValue) as discountTotal
            FROM tr_salesmenu a
            JOIN tr_saleshead b ON a.salesNum = b.salesNum AND b.statusID = 8 AND b.salesDate = '$salesDateStr'
            JOIN ms_promotionhead c ON c.promotionID = a.promotionDetailID
            WHERE a.statusID = 13
            AND a.salesNum NOT IN (
                SELECT a.salesNum 
                FROM tr_salespayment a
                JOIN ms_paymentmethod b ON a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 7
                JOIN tr_saleshead c on a.salesNum = c.salesNum AND c.statusID = 8 AND c.salesDate = '$salesDateStr'
            )
            GROUP BY c.notes;
            ");

        $modelDiscount = $querydDiscount->queryAll();

        $mpdf = new mPDF();

        $myHtml = require __DIR__ . '/report-klia-pdf.php';
        $mpdf->WriteHTML($myHtml);

        $path = Yii::$app->params['pathKlia'];
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            mkdir($path . '\Sent', 0777, true);
        }

        $documentName = "$path\\$locationCode" . "_" . date('dmY',
                strtotime($salesDateStr)) . "_ZRPT" . ".pdf";
        $mpdf->Output($documentName, \Mpdf\Output\Destination::FILE); //() kalo null nampilin
    }

    public function actionKliaReport($salesDateStr = '') {
        $this->actionKliaTxt($salesDateStr);
        $this->actionKliaPdf($salesDateStr);
    }

}
