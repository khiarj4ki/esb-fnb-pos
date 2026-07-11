<?php
use yii\db\Migration;

/**
 * Class m191023_114029_insert_procedure_spESBBilledOrder
 */
class m191023_114029_insert_procedure_spESBBilledOrder extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        $this->execute('DROP PROCEDURE IF EXISTS `spESBBilledOrder`');

        $createProcedureSql = <<< SQL
            CREATE DEFINER=`root`@`localhost` PROCEDURE `spESBBilledOrder`(IN salesDateParams CHAR(10), IN tableNameParams VARCHAR(100))
            BEGIN
                DECLARE row_number INT;
                DECLARE transSalesNum VARCHAR(100);
                SET @row_number = 0;
                SET @transSalesNum = '';
                SELECT * FROM (
                    SELECT 
                    tr_saleshead.branchID AS _ShopID,
                    CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                    tr_saleshead.salesDateIn AS _SaleDate,
                    CASE WHEN tr_saleshead.tableID > 0 THEN 1 ELSE 2 END AS _SaleModeID,
                    CASE WHEN tr_saleshead.tableID > 0 THEN 'Dine In' ELSE 'Take Away' END AS _SaleModeName,
                    tr_saleshead.tableID AS _TableID,
                    CAST(COALESCE(ms_table.tableName, tr_saleshead.salesNum) AS CHAR(100)) AS _TableName,
                    @row_number := CASE
                    WHEN @transSalesNum = tr_salesmenu.salesNum THEN @row_number + 1
                    ELSE 1
                    END AS _OrderLine,
                    CASE WHEN tr_salesmenu.menuGroupID = 0 THEN 0 
                    ELSE 1 END AS _IndentLevel,
                    tr_salesmenu.menuID AS _ProductID,
                    CAST(ms_menu.menuName AS CHAR(100)) AS _ProductName,
                    tr_salesmenu.qty AS _Qty,
                    CAST(tr_salesmenu.price - (tr_salesmenu.price * tr_salesmenu.discount / 100) AS DECIMAL(18,4)) AS _PricePerUnit,
                    tr_salesmenu.price AS _OrgPricePerUnit,
                    tr_salesmenu.discount AS _Disc,
                    @transSalesNum := CAST(tr_salesmenu.salesNum AS CHAR(100)) AS _ReferenceNo,
                    CAST(tr_salesmenu.salesNum AS CHAR(30)) AS _ReceiptNumber,
                    tr_saleshead.salesDateIn AS _OpenTime,
                    tr_saleshead.salesDateOut AS _PaidTime
                    FROM tr_salesmenu
                    LEFT JOIN tr_saleshead ON tr_saleshead.salesNum = tr_salesmenu.salesNum
                    LEFT JOIN ms_menu ON ms_menu.menuID = tr_salesmenu.menuID
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
                                CAST(tr_saleshead.salesDateIn AS DATE) = salesDateParams
                            ELSE 
                                CAST(tr_saleshead.salesDateIn AS DATE) = salesDateParams AND ms_table.tableName = tableNameParams
                            END
                        END
                    AND tr_saleshead.salesDateOut IS NOT NULL
                        
                        
                    UNION 

                    SELECT 
                    tr_saleshead.branchID AS _ShopID,
                    CAST(ms_branch.branchName AS CHAR(50)) AS  _ShopName,
                    tr_saleshead.salesDateIn AS _SaleDate,
                    CASE WHEN tr_saleshead.tableID > 0 THEN 1 ELSE 2 END AS _SaleModeID,
                    CASE WHEN tr_saleshead.tableID > 0 THEN 'Dine In' ELSE 'Take Away' END AS _SaleModeName,
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
                    @transSalesNum := CAST(tr_salesmenuextra.salesNum AS CHAR(100)) AS _ReferenceNo,
                    CAST(tr_salesmenuextra.salesNum AS CHAR(30)) AS _ReceiptNumber,
                    tr_saleshead.salesDateIn AS _OpenTime,
                    tr_saleshead.salesDateOut AS _PaidTime
                    FROM tr_salesmenuextra
                    LEFT JOIN tr_saleshead ON tr_saleshead.salesNum = tr_salesmenuextra.salesNum
                    LEFT JOIN ms_menuextra ON ms_menuextra.menuExtraID = tr_salesmenuextra.menuExtraID
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
                                CAST(tr_saleshead.salesDateIn AS DATE) = salesDateParams
                            ELSE 
                                CAST(tr_saleshead.salesDateIn AS DATE) = salesDateParams AND ms_table.tableName = tableNameParams
                            END
                        END
                    AND tr_saleshead.salesDateOut IS NOT NULL
                ) a
                ORDER BY 
                _PaidTime DESC,
                _SaleDate,
                _SaleModeID,
                _TableName;
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
