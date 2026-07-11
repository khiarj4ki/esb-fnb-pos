<?php
use app\models\SalesMenuCompletion;
use yii\db\Migration;

/**
 * Class m191112_071634_create_tr_salesmenucompletion
 */
class m191112_071634_create_tr_salesmenucompletion extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuCompletion::tableName(), true) === null) {
            $this->createTable(SalesMenuCompletion::tableName(),
                [
                'ID' => $this->primaryKey(),
                'localID' => $this->integer(),
                'salesNum' => $this->string(20)->notNull(),
                'salesMenuID' => $this->integer()->notNull(),
                'qty' => $this->decimal(20, 4),
                'completedDate' => $this->dateTime(),
                'typeID' => $this->integer(),
                'startDate' => $this->dateTime(),
                'syncDate' => $this->dateTime()
            ]);

            $this->createIndex('idx_tr_salesmenucompletion_salesNum',
                SalesMenuCompletion::tableName(), 'salesNum');
            $this->createIndex('idx_tr_salesmenucompletion_salesMenuID',
                SalesMenuCompletion::tableName(), 'salesMenuID');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuCompletion::tableName(), true) !== null) {
            $this->dropTable(SalesMenuCompletion::tableName());
        }
    }

}
