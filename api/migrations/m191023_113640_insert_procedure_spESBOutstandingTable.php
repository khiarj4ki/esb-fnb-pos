<?php
use yii\db\Migration;

/**
 * Class m191023_113640_insert_procedure_spESBOutstandingTable
 */
class m191023_113640_insert_procedure_spESBOutstandingTable extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        $this->execute('DROP PROCEDURE IF EXISTS `spESBOutstandingTable`');

        $createProcedureSql = <<< SQL
            CREATE DEFINER=`root`@`localhost` PROCEDURE `spESBOutstandingTable`(
                IN salesDateParams CHAR(10),
                IN tableNameParams VARCHAR(100),
                IN branchParams VARCHAR(50)
            )
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
                            @transSalesNum := CAST(tr_saleshead.salesNum AS CHAR(100)) AS _OrderID,
                            ms_branch.branchCode AS _ShopID,
                            CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                            tr_saleshead.salesDate AS _SalesDate,
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
                            @transSalesNum := CAST(tr_salesmenu.salesNum AS CHAR(100)) AS _ReferenceNo
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
                        LEFT JOIN tr_saleslink slk ON tr_saleshead.salesNum = slk.linkSalesNum
                        LEFT JOIN tr_salesmenu smu ON smu.localID = tr_salesmenu.menuRefID AND smu.salesNum = tr_salesmenu.salesNum
                        WHERE slk.linkSalesNum IS NULL AND CASE WHEN salesDateParams = '' THEN 
                                1 = 1
                            ELSE
                                CAST(tr_saleshead.salesDate AS DATE)= salesDateParams
                            END
                        AND tr_saleshead.salesDateOut IS NULL AND ms_branch.branchCode = branchParams
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
                            tr_saleshead.salesDate,
                            tr_saleshead.salesDateOut
                    )
                    UNION ALL
                    (
                        SELECT 
                            @transSalesNum := CAST(tr_saleshead.salesNum AS CHAR(100)) AS _OrderID,
                            ms_branch.branchCode AS _ShopID,
                            CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                            tr_saleshead.salesDate AS _SalesDate,
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
                            @transSalesNum := CAST(tr_saleshead.salesNum AS CHAR(100)) AS _ReferenceNo
                        FROM tr_saleshead
                        JOIN tr_saleslink ON tr_saleshead.salesNum = tr_saleslink.salesNum
                        LEFT JOIN tr_saleshead slt ON tr_saleslink.linkSalesNum = slt.salesNum
                        LEFT JOIN tr_salesmenu smlt ON slt.salesNum = smlt.salesNum
                        LEFT JOIN ms_table lt ON slt.tableID = lt.tableID
                        LEFT JOIN ms_menu ON ms_menu.menuID = smlt.menuID
                        LEFT JOIN ms_branch ON tr_saleshead.branchID = ms_branch.branchID
                        LEFT JOIN ms_table ON tr_saleshead.tableID = ms_table.tableID
                        LEFT JOIN tr_salesmenu smu ON smu.localID = smlt.menuRefID AND smu.salesNum = smlt.salesNum
                        WHERE CASE WHEN salesDateParams = '' THEN 
                                1 = 1
                            ELSE
                                CAST(tr_saleshead.salesDate AS DATE)= salesDateParams
                            END
                        AND tr_saleshead.salesDateOut IS NULL AND ms_branch.branchCode = branchParams
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
                            tr_saleshead.salesDate,
                            tr_saleshead.salesDateOut
                    )
                    UNION ALL
                    (
                        SELECT 
                        @transSalesNum := CAST(tr_salesmenuextra.salesNum AS CHAR(100)) AS _OrderID,
                        tr_saleshead.branchID AS _ShopID,
                        CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                        tr_saleshead.salesDateIn AS _SalesDate,
                        CASE WHEN tr_saleshead.tableID > 0 THEN 1 ELSE 2 END AS _SalesModeID,
                        CASE WHEN tr_saleshead.tableID > 0 THEN 'Dine In' ELSE 'Take Away' END AS _SalesModeName,
                        tr_saleshead.tableID AS _TableID,
                        CAST(COALESCE(ms_table.tableName, tr_saleshead.salesNum) AS CHAR(100)) AS _TableName,
                        @row_number := CASE
                            WHEN @transSalesNum = tr_salesmenuextra.salesNum THEN @row_number + 1
                            ELSE 1
                        END AS _OrderLine,
                        0 AS _IndentLevel,
                        tr_salesmenuextra.menuExtraID AS _ProductID,
                        CAST(ms_menuextra.menuExtraName AS CHAR(100)) AS _ProductName,
                        tr_salesmenuextra.qty AS _Qty,
                        CAST(tr_salesmenuextra.price - (tr_salesmenuextra.price * tr_salesmenuextra.discount / 100) AS DECIMAL(18,4)) AS _PricePerUnit,
                        tr_salesmenuextra.price AS _OrgPricePerUnit,
                        tr_salesmenuextra.discount AS _Disc,
                        @transSalesNum := CAST(tr_salesmenuextra.salesNum AS CHAR(100)) AS _ReferenceNo
                        FROM tr_salesmenuextra
                        LEFT JOIN tr_saleshead ON tr_saleshead.salesNum = tr_salesmenuextra.salesNum
                        LEFT JOIN ms_menuextra ON ms_menuextra.menuID = tr_salesmenuextra.menuExtraID
                        LEFT JOIN ms_branch ON tr_saleshead.branchID = ms_branch.branchID
                        LEFT JOIN ms_table ON tr_saleshead.tableID = ms_table.tableID
                        WHERE CASE WHEN salesDateParams = '' THEN 
                                CASE WHEN tableNameParams = '' THEN
                                    1 = 1
                                ELSE 
                                    ms_table.tableName = tableNameParams
                                END
                            ELSE
                                CASE WHEN tableNameParams = '' THEN
                                    CAST(tr_saleshead.salesDateIn AS DATE)= salesDateParams
                                ELSE 
                                    CAST(tr_saleshead.salesDateIn AS DATE) = salesDateParams AND ms_table.tableName = tableNameParams
                                END
                            END
                        AND tr_saleshead.salesDateOut IS NULL
                    )
                ) a
                    WHERE a._TableName LIKE CONCAT('%',tableNameParams,'%')
                    ORDER BY
                    a._SalesDate DESC,
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
