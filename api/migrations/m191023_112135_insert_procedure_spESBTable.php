<?php
use yii\db\Migration;

/**
 * Class m191023_112135_insert_procedure_spESBTable
 */
class m191023_112135_insert_procedure_spESBTable extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        $this->execute('DROP PROCEDURE IF EXISTS `spESBTable`');

        $createProcedureSql = <<< SQL
            CREATE DEFINER=`root`@`localhost` PROCEDURE `spESBTable`()
            BEGIN
                SELECT 
                    tableID AS _TableID,
                    tableID AS _TableNumber,
                    CAST(tableName AS CHAR(100)) AS _TableName
                FROM 
                    ms_table

                UNION ALL

                SELECT 
                    CAST(SUBSTRING(tr_saleshead.salesNum, 4) AS UNSIGNED) AS _TableID,
                    CAST(SUBSTRING(tr_saleshead.salesNum, 4) AS UNSIGNED) AS _TableNumber,
                    CAST(tr_saleshead.salesNum AS CHAR(100)) AS _TableName
                FROM
                    tr_saleshead 
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
