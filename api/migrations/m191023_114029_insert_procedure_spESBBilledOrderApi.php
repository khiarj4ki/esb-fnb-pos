<?php
use yii\db\Migration;

/**
 * Class m191023_114029_insert_procedure_spESBBilledOrderApi
 */
class m191023_114029_insert_procedure_spESBBilledOrderApi extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        $this->execute('DROP PROCEDURE IF EXISTS `spESBBilledOrderApi`');

        $createProcedureSql = <<< SQL
            CREATE DEFINER=`root`@`localhost` PROCEDURE `spESBBilledOrderApi`(IN salesNumParams VARCHAR(100))
            BEGIN
            DECLARE row_number INT;
            DECLARE transSalesNum VARCHAR(100);
            SET @row_number = 0;
            SET @transSalesNum = '';
            SELECT a.*
            FROM
            (
                (
                    SELECT 
                        ms_branch.branchCode AS _ShopID,
                        CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                        tr_saleshead.salesDateIn AS _SalesDate,
                        CASE WHEN tr_saleshead.tableID > 0 THEN 1 ELSE 2 END AS _SalesModeID,
                        CASE WHEN tr_saleshead.tableID > 0 THEN 'Dine In' ELSE 'Take Away' END AS _SalesModeName,
                        CASE 
                        WHEN tr_salesmergetable.salesNum IS NULL AND tr_saleslink.salesNum IS NULL THEN tr_saleshead.tableID
                        WHEN tr_salesmergetable.salesNum IS NOT NULL THEN CONCAT(tr_saleshead.tableID, ',', GROUP_CONCAT(tr_salesmergetable.tableID))
                        WHEN tr_saleslink.salesNum IS NOT NULL THEN CONCAT(tr_saleshead.tableID, ',', GROUP_CONCAT(slt.tableID))
                        END AS _TableID,
                        CASE 
                        WHEN tr_salesmergetable.salesNum IS NULL AND tr_saleslink.salesNum IS NULL THEN CAST(COALESCE(ms_table.tableName, tr_saleshead.billNum) AS CHAR(100))
                        WHEN tr_salesmergetable.salesNum IS NOT NULL THEN CONCAT(CAST(COALESCE(ms_table.tableName, tr_saleshead.salesNum) AS CHAR(100)), ',', GROUP_CONCAT(mt.tableName))
                        WHEN tr_saleslink.salesNum IS NOT NULL THEN CONCAT(CAST(COALESCE(ms_table.tableName, tr_saleshead.salesNum) AS CHAR(100)), ',', GROUP_CONCAT(lt.tableName))
                        END AS _TableName,
                        @row_number := CASE
                        WHEN @transSalesNum = tr_salesmenu.salesNum THEN @row_number + 1
                        ELSE 1
                        END AS _OrderLine,
                        CASE WHEN tr_salesmenu.menuGroupID = 0 THEN 0 
                        ELSE 1 END AS _IndentLevel,
                        tr_salesmenu.menuID AS _ProductID,
                        CAST(ms_menu.menuName AS CHAR(100)) AS _ProductName,
                        tr_salesmenu.qty * COALESCE(smu.qty, 1) AS _Qty,
                        CAST(tr_salesmenu.price - (tr_salesmenu.price * tr_salesmenu.discount / 100) AS DECIMAL(18,4)) AS _PricePerUnit,
                        tr_salesmenu.price AS _OrgPricePerUnit,
                        tr_salesmenu.discount AS _Disc,
                        @transSalesNum := CAST(tr_salesmenu.salesNum AS CHAR(100)) AS _ReferenceNo,
                        CAST(tr_saleshead.billNum AS CHAR(30)) AS _ReceiptNumber,
                        tr_saleshead.salesDateIn AS _OpenTime,
                        tr_saleshead.salesDateOut AS _PaidTime
                    FROM tr_salesmenu
                    LEFT JOIN tr_saleshead ON tr_saleshead.salesNum = tr_salesmenu.salesNum
                    LEFT JOIN ms_menu ON ms_menu.menuID = tr_salesmenu.menuID
                    LEFT JOIN ms_branch ON tr_saleshead.branchID = ms_branch.branchID
                    LEFT JOIN ms_table ON tr_saleshead.tableID = ms_table.tableID
                    LEFT JOIN tr_salesmergetable ON tr_saleshead.salesNum = tr_salesmergetable.salesNum
                    LEFT JOIN ms_table mt ON tr_salesmergetable.tableID = mt.tableID
                    LEFT JOIN tr_saleslink ON tr_saleshead.salesNum = tr_saleslink.salesNum
                    LEFT JOIN tr_saleshead slt ON tr_saleslink.linkSalesNum = slt.salesNum
                    LEFT JOIN ms_table lt ON slt.tableID = lt.tableID
                    LEFT JOIN tr_salesmenu smu ON smu.localID = tr_salesmenu.menuRefID AND smu.salesNum = tr_salesmenu.salesNum
                    WHERE tr_saleshead.salesDateOut IS NOT NULL AND tr_saleshead.salesNum = salesNumParams
                    GROUP BY
                        tr_saleshead.salesNum,
                        tr_saleshead.branchID,
                        ms_branch.branchName,
                        tr_saleshead.tableID,
                        ms_table.tableName,
                        tr_salesmenu.salesNum,
                        tr_salesmenu.menuGroupID,
                        tr_salesmenu.menuID,
                        ms_menu.menuName,
                        tr_salesmenu.qty,
                        smu.qty,
                        tr_salesmenu.price,
                        tr_salesmenu.discount,
                        tr_saleshead.billNum,
                        tr_saleshead.salesDateIn,
                        tr_saleshead.salesDateOut
                )
                UNION ALL
                (
                    SELECT 
                        ms_branch.branchCode AS _ShopID,
                        CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                        tr_saleshead.salesDateIn AS _SalesDate,
                        CASE WHEN tr_saleshead.tableID > 0 THEN 1 ELSE 2 END AS _SalesModeID,
                        CASE WHEN tr_saleshead.tableID > 0 THEN 'Dine In' ELSE 'Take Away' END AS _SalesModeName,
                        CASE 
                        WHEN tr_saleslink.salesNum IS NULL THEN tr_saleshead.tableID
                        WHEN tr_saleslink.salesNum IS NOT NULL THEN CONCAT(tr_saleshead.tableID, ',', GROUP_CONCAT(slt.tableID)) 
                        END AS _TableID,
                        CASE 
                        WHEN tr_saleslink.salesNum IS NULL THEN CAST(COALESCE(ms_table.tableName, tr_saleshead.billNum) AS CHAR(100))
                        WHEN tr_saleslink.salesNum IS NOT NULL THEN CONCAT(CAST(COALESCE(ms_table.tableName, tr_saleshead.billNum) AS CHAR(100)), ',', GROUP_CONCAT(lt.tableName))
                        END AS _TableName,
                        @row_number := CASE
                        WHEN @transSalesNum = smlt.salesNum THEN @row_number + 1
                        ELSE 1
                        END AS _OrderLine,
                        CASE WHEN smlt.menuGroupID = 0 THEN 0 
                        ELSE 1 END AS _IndentLevel,
                        smlt.menuID AS _ProductID,
                        CAST(ms_menu.menuName AS CHAR(100)) AS _ProductName,
                        smlt.qty * COALESCE(smu.qty, 1) AS _Qty,
                        CAST(smlt.price - (smlt.price * smlt.discount / 100) AS DECIMAL(18,4)) AS _PricePerUnit,
                        smlt.price AS _OrgPricePerUnit,
                        smlt.discount AS _Disc,
                        @transSalesNum := CAST(smlt.salesNum AS CHAR(100)) AS _ReferenceNo,
                        CAST(tr_saleshead.billNum AS CHAR(30)) AS _ReceiptNumber,
                        tr_saleshead.salesDateIn AS _OpenTime,
                        tr_saleshead.salesDateOut AS _PaidTime
                    FROM tr_saleshead
                    JOIN tr_saleslink ON tr_saleshead.salesNum = tr_saleslink.salesNum
                    LEFT JOIN tr_saleshead slt ON tr_saleslink.linkSalesNum = slt.salesNum
                    LEFT JOIN tr_salesmenu smlt ON slt.salesNum = smlt.salesNum
                    LEFT JOIN ms_table lt ON slt.tableID = lt.tableID
                    LEFT JOIN ms_menu ON ms_menu.menuID = smlt.menuID
                    LEFT JOIN ms_branch ON tr_saleshead.branchID = ms_branch.branchID
                    LEFT JOIN ms_table ON tr_saleshead.tableID = ms_table.tableID
                    LEFT JOIN tr_salesmenu smu ON smu.localID = smlt.menuRefID AND smu.salesNum = smlt.salesNum
                    WHERE tr_saleshead.salesDateOut IS NOT NULL AND tr_saleshead.salesNum = salesNumParams
                    GROUP BY
                        tr_saleshead.salesNum,
                        tr_saleshead.branchID,
                        ms_branch.branchName,
                        tr_saleshead.tableID,
                        ms_table.tableName,
                        smlt.salesNum,
                        smlt.menuGroupID,
                        smlt.menuID,
                        ms_menu.menuName,
                        smlt.qty,
                        smu.qty,
                        smlt.price,
                        smlt.discount,
                        tr_saleshead.billNum,
                        tr_saleshead.salesDateIn,
                        tr_saleshead.salesDateOut
                )
            ) a
            ORDER BY 
                a._PaidTime DESC,
                a._OpenTime DESC,
                a._SalesModeID,
                a._TableName;
            END;
SQL;
        $this->execute($createProcedureSql);
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
