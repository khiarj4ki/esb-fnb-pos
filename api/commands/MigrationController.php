<?php

namespace app\commands;

use app\components\AppHelper;
use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\console\controllers\BaseMigrateController;

class MigrationController extends BaseMigrateController {
    /**
     * Maximum length of a migration name.
     * @since 2.0.13
     */
    const MAX_NAME_LENGTH = 180;
    /**
     * @var string the name of the table for keeping applied migration information.
     */
    public $migrationTable = '{{%migration}}';
    /**
     * {@inheritdoc}
     */
    public $templateFile = '@yii/views/migration.php';
    /**
     * @var array a set of template paths for generating migration code automatically.
     *
     * The key is the template type, the value is a path or the alias. Supported types are:
     * - `create_table`: table creating template
     * - `drop_table`: table dropping template
     * - `add_column`: adding new column template
     * - `drop_column`: dropping column template
     * - `create_junction`: create junction template
     *
     * @since 2.0.7
     */
    public $generatorTemplateFiles = [
        'create_table' => '@yii/views/createTableMigration.php',
        'drop_table' => '@yii/views/dropTableMigration.php',
        'add_column' => '@yii/views/addColumnMigration.php',
        'drop_column' => '@yii/views/dropColumnMigration.php',
        'create_junction' => '@yii/views/createTableMigration.php',
    ];
    /**
     * @var bool indicates whether the table names generated should consider
     * the `tablePrefix` setting of the DB connection. For example, if the table
     * name is `post` the generator wil return `{{%post}}`.
     * @since 2.0.8
     */
    public $useTablePrefix = true;
    /**
     * @var array column definition strings used for creating migration code.
     *
     * The format of each definition is `COLUMN_NAME:COLUMN_TYPE:COLUMN_DECORATOR`. Delimiter is `,`.
     * For example, `--fields="name:string(12):notNull:unique"`
     * produces a string column of size 12 which is not null and unique values.
     *
     * Note: primary key is added automatically and is named id by default.
     * If you want to use another name you may specify it explicitly like
     * `--fields="id_key:primaryKey,name:string(12):notNull:unique"`
     * @since 2.0.7
     */
    public $fields = [];
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection to use
     * when applying migrations. Starting from version 2.0.3, this can also be a configuration array
     * for creating the object.
     */
    public $db = 'db';
    /**
     * @var string the comment for the table being created.
     * @since 2.0.14
     */
    public $comment = '';

    public $count = 0;

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param \yii\base\Action $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $this->db = Instance::ensure($this->db, Connection::className());
            return true;
        }

        return false;
    }

    public function afterAction($action, $result)
    {
        $dbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);
        if (strpos($dbName, '_trial') === false)
        {
            $this->runMigrate($action->actionMethod);
        }
    }

    public function runMigrate($action)
    {
        $db = $this->db;
        $dbName = AppHelper::getDsnAttribute('dbname', $db->dsn);
        $dbHost = AppHelper::getDsnAttribute('host', $db->dsn);

        if (strpos($dbName, '_trial') !== false) {
            $dbName = str_replace('_trial', '', $dbName);
        } else {
            $dbName = $dbName . "_trial";
        }

        $checkSchemaExist = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$dbName'";

        if ($action === 'actionUp') {            
            if (!$db->createCommand($checkSchemaExist)->queryScalar())
            {
                $db->createCommand("CREATE DATABASE IF NOT EXISTS $dbName")->query();
            }
            
            $db->close();
            $db->dsn = "mysql:host=$dbHost;dbname=$dbName";
            $db->open();

            Yii::$app->runAction('migrate',
                [
                    'interactive' => 0
                ]);
            Yii::$app->cache->flush();

        } else if ($action === 'actionDown') {
            if ($db->createCommand($checkSchemaExist)->queryScalar()) {
                $db->close();
                $db->dsn = "mysql:host=$dbHost;dbname=$dbName";
                $db->open();
        
                Yii::$app->runAction('migrate/down',
                    [
                        'interactive' => 0
                    ]);
                Yii::$app->cache->flush();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = (new Query())
            ->select(['version', 'apply_time'])
            ->from($this->migrationTable)
            ->orderBy(['apply_time' => SORT_DESC, 'version' => SORT_DESC]);

        if (empty($this->migrationNamespaces)) {
            $query->limit($limit);
            $rows = $query->all($this->db);
            $history = ArrayHelper::map($rows, 'version', 'apply_time');
            unset($history[self::BASE_MIGRATION]);
            return $history;
        }

        $rows = $query->all($this->db);

        $history = [];
        foreach ($rows as $key => $row) {
            if ($row['version'] === self::BASE_MIGRATION) {
                continue;
            }
            if (preg_match('/m?(\d{6}_?\d{6})(\D.*)?$/is', $row['version'], $matches)) {
                $time = str_replace('_', '', $matches[1]);
                $row['canonicalVersion'] = $time;
            } else {
                $row['canonicalVersion'] = $row['version'];
            }
            $row['apply_time'] = (int) $row['apply_time'];
            $history[] = $row;
        }

        usort($history, function ($a, $b) {
            if ($a['apply_time'] === $b['apply_time']) {
                if (($compareResult = strcasecmp($b['canonicalVersion'], $a['canonicalVersion'])) !== 0) {
                    return $compareResult;
                }

                return strcasecmp($b['version'], $a['version']);
            }

            return ($a['apply_time'] > $b['apply_time']) ? -1 : +1;
        });

        $history = array_slice($history, 0, $limit);

        $history = ArrayHelper::map($history, 'version', 'apply_time');

        return $history;
    }

    /**
     * {@inheritdoc}
     */
    protected function addMigrationHistory($version)
    {
        $command = $this->db->createCommand();
        $command->insert($this->migrationTable, [
            'version' => $version,
            'apply_time' => time(),
        ])->execute();
    }

    /**
     * {@inheritdoc}
     */
    protected function removeMigrationHistory($version)
    {
        $command = $this->db->createCommand();
        $command->delete($this->migrationTable, [
            'version' => $version,
        ])->execute();
    }

    /**
     * Creates the migration history table.
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->schema->getRawTableName($this->migrationTable);
        $this->stdout("Creating migration history table \"$tableName\"...", Console::FG_YELLOW);
        $this->db->createCommand()->createTable($this->migrationTable, [
            'version' => 'varchar(' . static::MAX_NAME_LENGTH . ') NOT NULL PRIMARY KEY',
            'apply_time' => 'integer',
        ])->execute();
        $this->db->createCommand()->insert($this->migrationTable, [
            'version' => self::BASE_MIGRATION,
            'apply_time' => time(),
        ])->execute();
        $this->stdout("Done.\n", Console::FG_GREEN);
    }
}
