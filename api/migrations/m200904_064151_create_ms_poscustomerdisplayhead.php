<?php

use yii\db\Migration;
use app\models\MsPosCustomerDisplayHead;

/**
 * Class m200904_064151_create_ms_poscustomerdisplayhead
 */
class m200904_064151_create_ms_poscustomerdisplayhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MsPosCustomerDisplayHead::tableName(),
                true) === null) {
            $this->createTable(MsPosCustomerDisplayHead::tableName(),
                [
                    'posCustomerDisplayID' => $this->integer(11)->notNull(),
                    'posCustomerDisplayName' => $this->string(50)->notNull(),
                    'flagActive' => $this->integer(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(100)->null(),
                    'editedDate' => $this->dateTime()->null(),
                ]);

                $this->addPrimaryKey('PRIMARYKEY',
                MsPosCustomerDisplayHead::tableName(),
                ['posCustomerDisplayID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MsPosCustomerDisplayHead::tableName(),
                true) !== null) {
            $this->dropTable(MsPosCustomerDisplayHead::tableName());
        }
    }
}
