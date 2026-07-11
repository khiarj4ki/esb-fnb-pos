<?php

use app\models\LkExternalMemberShipType;
use yii\db\Migration;

/**
 * Class m210727_031410_create_lk_externalmembershiptype_pos
 */
class m210727_031410_create_lk_externalmembershiptype_pos extends Migration
{

    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(LkExternalMemberShipType::tableName(), true) === null) {
            $this->createTable(LkExternalMemberShipType::tableName(),
            [
                'externalMembershipTypeID' => $this->string(20)->notNull(),
                'externalMembershipTypeName' => $this->string(50)->defaultValue(null),
            ]);

            $this->batchInsert(LkExternalMemberShipType::tableName(),
                ['externalMembershipTypeID', 'externalMembershipTypeName'],
                [
                    ['general', 'General'],
                    ['memberid', 'Member.id'],
                    ['esbloyalty', 'ESB Loop'],
                    ['tada', 'TADA'],
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(LkExternalMemberShipType::tableName(), true) !== null) {
            $this->dropTable(LkExternalMemberShipType::tableName());
        }
    }
}
