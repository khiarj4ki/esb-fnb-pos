<?php

use yii\db\Migration;
use app\models\MsPosCustomerDisplayDetail;

/**
 * Class m200909_100420_fix_msposcustomerdisplaydetail_auto_increment
 */
class m200909_100420_fix_msposcustomerdisplaydetail_auto_increment extends Migration
{
    /**
    * @inheritdoc
    */
    public function up() {
        if ($this->db->getTableSchema(MsPosCustomerDisplayDetail::tableName(),
                true) !== null) {
            $this->dropTable(MsPosCustomerDisplayDetail::tableName());
        }

        if ($this->db->getTableSchema(MsPosCustomerDisplayDetail::tableName(),
                true) === null) {
            $this->createTable(MsPosCustomerDisplayDetail::tableName(),
                [
                    'ID' => $this->integer(11)->notNull(),
                    'posCustomerDisplayID' => $this->integer(11)->notNull(),
                    'imageUrl' => $this->text()->notNull()
                ]);
            
            $this->addPrimaryKey('PRIMARYKEY',
                MsPosCustomerDisplayDetail::tableName(),
            ['ID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        
    }
}
