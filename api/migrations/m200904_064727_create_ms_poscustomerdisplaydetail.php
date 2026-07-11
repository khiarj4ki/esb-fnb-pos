<?php

use yii\db\Migration;
use app\models\MsPosCustomerDisplayDetail;
/**
 * Class m200904_064727_create_ms_poscustomerdisplaydetail
 */
class m200904_064727_create_ms_poscustomerdisplaydetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MsPosCustomerDisplayDetail::tableName(),
                true) === null) {
            $this->createTable(MsPosCustomerDisplayDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'posCustomerDisplayID' => $this->integer(11)->notNull(),
                    'imageUrl' => $this->text()->notNull()
                ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MsPosCustomerDisplayDetail::tableName(),
                true) !== null) {
            $this->dropTable(MsPosCustomerDisplayDetail::tableName());
        }
    }
}
