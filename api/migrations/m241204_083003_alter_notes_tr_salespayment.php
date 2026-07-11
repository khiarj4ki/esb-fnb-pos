<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m241204_083003_alter_notes_tr_salespayment
 */
class m241204_083003_alter_notes_tr_salespayment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('notes') !== null) {
            $this->alterColumn(SalesPayment::tableName(), 'notes', 
                $this->string(1000)->append("CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL")
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        //No migration down
    }
}
