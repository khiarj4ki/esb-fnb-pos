<?php

use app\models\MenuExtra;
use yii\db\Migration;

/**
 * Class m241017_070906_alter_ms_menu_extra_chinesse_character_utf8
 */
class m241017_070906_alter_ms_menu_extra_chinesse_character_utf8 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $table = MenuExtra::tableName();
        $columns = ['menuExtraName', 'menuExtraShortName'];

        foreach ($columns as $column) {
            $charset = $this->db->createCommand("
                SELECT CCSA.character_set_name
                FROM information_schema.`COLUMNS` C
                JOIN information_schema.`TABLES` T
                ON C.table_schema = T.table_schema
                AND C.table_name = T.table_name
                JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA
                ON CCSA.collation_name = C.collation_name
                WHERE C.table_name = '{$table}'
                AND C.column_name = '{$column}'
            ")->queryScalar();
    
            if ($charset !== 'utf8') {
                if ($column == 'menuExtraName') {
                    $this->alterColumn($table, $column, 'varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
                } else {
                    $this->alterColumn($table, $column, 'varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL');
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $table = MenuExtra::tableName();
        $columns = ['menuExtraName', 'menuExtraShortName'];

        foreach ($columns as $column) {
            $charset = $this->db->createCommand("
                SELECT CCSA.character_set_name
                FROM information_schema.`COLUMNS` C
                JOIN information_schema.`TABLES` T
                ON C.table_schema = T.table_schema
                AND C.table_name = T.table_name
                JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA
                ON CCSA.collation_name = C.collation_name
                WHERE C.table_name = '{$table}'
                AND C.column_name = '{$column}'
            ")->queryScalar();
    
            if ($charset !== 'latin1') {
                if ($column == 'menuExtraName') {
                    $this->alterColumn($table, $column, 'varchar(100) NOT NULL');
                } else {
                    $this->alterColumn($table, $column, 'varchar(30) DEFAULT NULL');
                }
            }
        }
    }
}
