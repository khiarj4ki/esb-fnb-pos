<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m210223_052134_update_saleshead_syncdate_issue
 */
class m210223_052134_update_saleshead_syncdate_issue extends Migration {

    /**
     * {@inheritdoc}
     */
    public function up() {
        $this->execute("UPDATE " . SalesHead::tableName() . " " .
                "SET syncDate = DATE_ADD(editedDate, INTERVAL 10 SECOND) " .
                "WHERE DATE_SUB(syncDate, INTERVAL 5 SECOND) < editedDate and salesDate < CAST(NOW() AS DATE);");
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
