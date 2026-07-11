<?php

use yii\db\Migration;
use app\components\AppHelper;

/**
 * Class m200804_064506_create_view_pajak_untuk_boga
 */
class m200804_064506_create_view_tax_jakarta extends Migration
{
    
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);
        $sql = "CREATE OR REPLACE
        ALGORITHM = UNDEFINED 
        SQL SECURITY DEFINER
        VIEW `$mainDbName`.`view_tax_jakarta` AS
            (SELECT 
                COALESCE(`a`.`billNum`, `a`.`salesNum`) AS `Sales_No`,
                COALESCE(`a`.`billNum`, `a`.`salesNum`) AS `Receipt_No`,
                CONCAT(DATE_FORMAT(`a`.`salesDateOut`, '%m/%d/%Y'),
                        ' ',
                        DATE_FORMAT(`a`.`salesDateOut`, '%T')) AS `Tanggal_Trx`,
            `a`.`subtotal` AS `Subtotal`,
                `a`.`otherTaxTotal` AS `Service_Charge`,
            `a`.`vatTotal` AS `Tax`,
            (`a`.`grandTotal` - `a`.`roundingTotal`) AS `Amount`,
                `a`.`salesDate` AS `SaleDate`,
                `b`.`branchCode` AS `OutletCode`,
                `b`.`branchName` AS `OutletName`,
                `a`.`branchID` AS `ShopID`
        
            FROM
                (`tr_saleshead` `a`
                JOIN `ms_branch` `b` ON ((`a`.`branchID` = `b`.`branchID`)))
            WHERE
                ((`a`.`statusID` = 8)
                    AND (NOT (`a`.`salesNum` IN (SELECT 
                        `a`.`salesNum`
                    FROM
                        ((`$mainDbName`.`tr_saleshead` `a`
                        JOIN `$mainDbName`.`tr_salespayment` `b` ON ((`a`.`salesNum` = `b`.`salesNum`)))
                        JOIN `$mainDbName`.`ms_paymentmethod` `c` ON (((`b`.`paymentMethodID` = `c`.`paymentMethodID`)
                            AND (`c`.`paymentMethodTypeID` = 7))))
                    GROUP BY `a`.`salesNum` UNION SELECT 
                        `a`.`salesNum`
                    FROM
                        (((`tr_saleshead` `a`
                        JOIN `$mainDbName`.`tr_saleslink` `b` ON ((`a`.`salesNum` = `b`.`linkSalesNum`)))
                        JOIN `$mainDbName`.`tr_salespayment` `c` ON ((`b`.`salesNum` = `c`.`salesNum`)))
                        JOIN `$mainDbName`.`ms_paymentmethod` `d` ON (((`c`.`paymentMethodID` = `d`.`paymentMethodID`)
                            AND (`d`.`paymentMethodTypeID` = 7))))
                    GROUP BY `a`.`salesNum`)))))";
        $this->execute($sql);
    }

    public function down()
    {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);
        $sql = "CREATE OR REPLACE
        ALGORITHM = UNDEFINED 
        SQL SECURITY DEFINER
        VIEW `$mainDbName`.`view_tax_jakarta` AS
            (SELECT 
                COALESCE(`a`.`billNum`, `a`.`salesNum`) AS `Sales_No`,
                COALESCE(`a`.`billNum`, `a`.`salesNum`) AS `Receipt_No`,
                CONCAT(DATE_FORMAT(`a`.`salesDateOut`, '%m/%d/%Y'),
                        ' ',
                        DATE_FORMAT(`a`.`salesDateOut`, '%T')) AS `Tanggal_Trx`,
            `a`.`subtotal` AS `Subtotal`,
                `a`.`otherTaxTotal` AS `Service_Charge`,
            `a`.`vatTotal` AS `Tax`,
            (`a`.`grandTotal` - `a`.`roundingTotal`) AS `Amount`,
                `a`.`salesDate` AS `SaleDate`,
                `b`.`branchCode` AS `OutletCode`,
                `b`.`branchName` AS `OutletName`,
                `a`.`branchID` AS `ShopID`
        
            FROM
                (`tr_saleshead` `a`
                JOIN `ms_branch` `b` ON ((`a`.`branchID` = `b`.`branchID`)))
            WHERE
                ((`a`.`statusID` = 8)
                    AND (NOT (`a`.`salesNum` IN (SELECT 
                        `a`.`salesNum`
                    FROM
                        ((`$mainDbName`.`tr_saleshead` `a`
                        JOIN `$mainDbName`.`tr_salespayment` `b` ON ((`a`.`salesNum` = `b`.`salesNum`)))
                        JOIN `$mainDbName`.`ms_paymentmethod` `c` ON (((`b`.`paymentMethodID` = `c`.`paymentMethodID`)
                            AND (`c`.`paymentMethodTypeID` = 7))))
                    GROUP BY `a`.`salesNum` UNION SELECT 
                        `a`.`salesNum`
                    FROM
                        (((`tr_saleshead` `a`
                        JOIN `$mainDbName`.`tr_saleslink` `b` ON ((`a`.`salesNum` = `b`.`linkSalesNum`)))
                        JOIN `$mainDbName`.`tr_salespayment` `c` ON ((`b`.`salesNum` = `c`.`salesNum`)))
                        JOIN `$mainDbName`.`ms_paymentmethod` `d` ON (((`c`.`paymentMethodID` = `d`.`paymentMethodID`)
                            AND (`d`.`paymentMethodTypeID` = 7))))
                    GROUP BY `a`.`salesNum`)))))";
        $this->execute($sql);
    }
   
}
