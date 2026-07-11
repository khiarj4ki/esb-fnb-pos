<?php
use yii\db\Migration;

/**
 * Class m191023_112135_insert_procedure_spESBTableApi
 */
class m191023_112135_insert_procedure_spESBTableApi extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        $this->execute('DROP PROCEDURE IF EXISTS `spESBTableApi`');

        $createProcedureSql = <<< SQL
            CREATE DEFINER=`root`@`localhost` PROCEDURE `spESBTableApi`(IN branchParams VARCHAR(20))
            BEGIN
                SELECT 
                    tableID AS _TableID,
                    tableID AS _TableNumber,
                    CAST(tableName AS CHAR(100)) AS _TableName
                FROM 
                    ms_table
                JOIN 
                    ms_tablesection on ms_table.tableSectionID = ms_tablesection.tableSectionID
                JOIN 
                    ms_branch on ms_tablesection.branchID = ms_branch.branchID and ms_branch.branchCode = branchParams

                UNION ALL

                SELECT
                    CAST(SUBSTRING(tr_saleshead.salesNum, 4) AS UNSIGNED) AS _TableID,
                    CAST(SUBSTRING(tr_saleshead.salesNum, 4) AS UNSIGNED) AS _TableNumber,
                    CAST(tr_saleshead.salesNum AS CHAR(100)) AS _TableName
                FROM
                    tr_saleshead 
                JOIN 
                    ms_branch on tr_saleshead.branchID = ms_branch.branchID and ms_branch.branchCode = branchParams
                WHERE tableID  = 0;
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
