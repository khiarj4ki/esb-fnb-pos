<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200406_024819_add_column_tr_saleshead
 */
class m200406_024819_add_column_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalAPI') === null) {
            $this->addColumn(SalesHead::tableName(), 'flagExternalAPI',
                $this->tinyInteger(1)->after('flagInclusive'));
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalMemberID') === null) {
            $this->addColumn(SalesHead::tableName(), 'flagExternalMemberID',
                $this->string(50)->after('flagExternalAPI'));
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalMemberPhone') === null) {
            $this->addColumn(SalesHead::tableName(), 'flagExternalMemberPhone',
                $this->string(20)->after('flagExternalMemberID'));
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalCardID') === null) {
            $this->addColumn(SalesHead::tableName(), 'flagExternalCardID',
                $this->string(50)->after('flagExternalMemberPhone'));
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalTransID') === null) {
            $this->addColumn(SalesHead::tableName(), 'externalTransID',
                $this->string(50)->after('flagExternalCardID'));
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalCancelTransID') === null) {
            $this->addColumn(SalesHead::tableName(), 'externalCancelTransID',
                $this->string(50)->after('externalTransID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalAPI') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'flagExternalAPI');
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalMemberID') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'flagExternalMemberID');
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalMemberPhone') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'flagExternalMemberPhone');
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('flagExternalCardID') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'flagExternalCardID');
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalTransID') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'externalTransID');
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalCancelTransID') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'externalCancelTransID');
        }
    }
}
