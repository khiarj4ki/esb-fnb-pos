<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m240925_023110_alter_tr_salespayment_up_length_notes_field
 */
class m240925_023110_alter_tr_salespayment_up_length_notes_field extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('notes') !== null) {
            $this->alterColumn(SalesPayment::tableName(), 'notes', $this->string(500));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        
    }
}
