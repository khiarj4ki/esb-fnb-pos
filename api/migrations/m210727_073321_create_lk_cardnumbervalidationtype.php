<?php

use app\models\LkCardNumberValidationType;
use yii\db\cubrid\Schema;
use yii\db\Migration;

/**
 * Class m210727_073321_create_lk_cardnumbervalidationtype
 */
class m210727_073321_create_lk_cardnumbervalidationtype extends Migration
{
     /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(LkCardNumberValidationType::tableName(), true) === null) {
            $this->createTable(LkCardNumberValidationType::tableName(),
                [
                'cardNumberValidationTypeID' => Schema::TYPE_PK . ' NOT NULL AUTO_INCREMENT',
                'cardNumberValidationName' => $this->string(50)->notNull()
            ]);


            $this->batchInsert(
                LkCardNumberValidationType::tableName(),
                ['cardNumberValidationTypeID', 'cardNumberValidationName'],
                [
                    ['1', 'Default'],
                    ['2', 'First and Last digit'],
                    ['3', 'First digit']
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(LkCardNumberValidationType::tableName(), true) !== null) {
            $this->dropTable(LkCardNumberValidationType::tableName());
        }
    }
}
