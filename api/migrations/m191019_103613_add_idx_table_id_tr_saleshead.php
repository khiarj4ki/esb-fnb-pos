<?php
use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m191019_103613_add_idx_table_id_tr_saleshead
 */
class m191019_103613_add_idx_table_id_tr_saleshead extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        try {
            $this->dropIndex('idx_saleshead_tableID', SalesHead::tableName());
        } catch (Exception $ex) {
            echo 'Index idx_saleshead_tableID does not exist.\n';
        }

        $this->createIndex('idx_saleshead_tableID', SalesHead::tableName(),
            'tableID');
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        try {
            $this->dropIndex('idx_saleshead_tableID', SalesHead::tableName());
        } catch (Exception $ex) {
            echo 'Index idx_saleshead_tableID does not exist.\n';
        }
    }

}
