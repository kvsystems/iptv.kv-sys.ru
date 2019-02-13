<?php
namespace Evie\Rest\System\Database;

use Evie\Rest\System\Record\Condition\Condition;
use Evie\Rest\System\Column\Reflection\ReflectedColumn;
use Evie\Rest\System\Column\Reflection\ReflectedTable;
use Evie\Rest\System\Middleware\Communication\VariableStore;
use Evie\Rest\System\Record\Condition\AndCondition;
use Evie\Rest\System\Record\Condition\ColumnCondition;

class GenericDB {

    const AUTHORIZATION_CONDITION = 'authorization.condition';
    const MYSQL_ENCODING          = 'utf8mb4';
    const PG_ENCODING             = 'UTF8';

    const DEFAULT_DRIVER    = 'mysql';
    const PG_SQL_DRIVER     = 'pg_sql';
    const SQL_SRV_DRIVER    = 'sql_srv';

    private $_driver        = null;
    private $_database      = null;
    private $_pdo           = null;
    private $_reflection    = null;
    private $_columns       = null;
    private $_conditions    = null;
    private $_converter     = null;

    private function _getDsn($address = null, $port = null, $database = null)  {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return "$this->_driver:host=$address port=$port dbname=$database options='--client_encoding=".self::PG_ENCODING."'";
            case self::SQL_SRV_DRIVER:
                return "$this->_driver:Server=$address,$port;Database=$database";
            default:
                return "$this->_driver:host=$address;port=$port;dbname=$database;charset=".self::MYSQL_ENCODING;
        }
    }

    private function _getCommands() {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return [
                    "SET NAMES 'UTF8';",
                ];
            case self::SQL_SRV_DRIVER:
                return [];
            default:
                return [
                    'SET SESSION sql_warnings=1;',
                    'SET NAMES utf8mb4;',
                    'SET SESSION sql_mode = "ANSI,TRADITIONAL";',
                ];
        }
    }

    private function _getOptions()  {
        $options = array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        );
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return $options + [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_PERSISTENT => true,
                ];
            case self::SQL_SRV_DRIVER:
                return $options + [
                    \PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
                ];
            default:
                return $options + [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::MYSQL_ATTR_FOUND_ROWS => true,
                    \PDO::ATTR_PERSISTENT => true,
                ];
        }
    }

    private function _addAuthorizationCondition(Condition $condition)   {
        $newCondition = VariableStore::get(self::AUTHORIZATION_CONDITION);
        return $newCondition
            ? AndCondition::fromArray([$newCondition, $condition])
            : $condition;
    }

    public function __construct($driver = null, $host = null, $port = null, $database = null, $username = null, $password = null)   {
        $this->_driver      = $driver;
        $this->_database    = $database;
        $dsn                = $this->_getDsn($host, $port, $database);
        $options            = $this->_getOptions();
        $this->_pdo         = new \PDO($dsn, $username, $password, $options);
        $commands           = $this->_getCommands();

        foreach ($commands as $command) {
            $this->_pdo->query($command);
        }

        $this->_reflection  = new GenericReflection($this->_pdo, $driver, $database);
        $this->_definition  = new GenericDefinition($this->_pdo, $driver, $database);
        $this->_conditions  = new ConditionsBuilder($driver);
        $this->_columns     = new ColumnsBuilder($driver);
        $this->_converter   = new DataConverter($driver);
    }

    public function pdo()   {
        return $this->_pdo;
    }

    public function reflection()    {
        return $this->_reflection;
    }

    public function definition()    {
        return $this->_definition;
    }

    public function createSingle(ReflectedTable $table = null, array $columnValues = [])  {
        $this->_converter->convertColumnValues($table, $columnValues);
        $insertColumns  = $this->_columns->getInsert($table, $columnValues);
        $tableName      = $table->getName();
        $pkName         = $table->getPk()->getName();
        $parameters     = array_values($columnValues);
        $sql            = 'INSERT INTO "' . $tableName . '" ' . $insertColumns;
        $stmt           = $this->query($sql, $parameters);
        if (isset($columnValues[$pkName])) {
            return $columnValues[$pkName];
        }
        switch ($this->_driver) {
            default:
                $stmt   = $this->query('SELECT LAST_INSERT_ID()', []);
                break;
        }
        return $stmt->fetchColumn(0);
    }

    public function getSingle(ReflectedTable $table = null, array $columnNames = [], $id = null) {
        $parameters     = [];
        $selectColumns  = $this->_columns->getSelect($table, $columnNames);
        $tableName      = $table->getName();
        $condition      = new ColumnCondition($table->getPk(), 'eq', $id);
        $condition      = $this->_addAuthorizationCondition($condition);
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $sql            = 'SELECT ' . $selectColumns . ' FROM "' . $tableName . '" ' . $whereClause;
        $stmt           = $this->query($sql, $parameters);
        $record         = $stmt->fetch() ?: null;
        if ($record === null) {
            return null;
        }
        $records        = array($record);
        $this->_converter->convertRecords($table, $columnNames, $records);
        return $records[0];
    }

    public function selectMultiple(ReflectedTable $table = null, array $columnNames = [], array $ids = [])    {
        $parameters     = [];
        if (count($ids) == 0) {
            return [];
        }
        $selectColumns  = $this->_columns->getSelect($table, $columnNames);
        $tableName      = $table->getName();
        $condition      = new ColumnCondition($table->getPk(), 'in', implode(',', $ids));
        $condition      = $this->_addAuthorizationCondition($condition);
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $sql            = 'SELECT ' . $selectColumns . ' FROM "' . $tableName . '" ' . $whereClause;
        $stmt           = $this->query($sql, $parameters);
        $records        = $stmt->fetchAll();
        $this->_converter->convertRecords($table, $columnNames, $records);
        return $records;
    }

    public function selectCount(ReflectedTable $table = null, Condition $condition = null)   {
        $parameters     = [];
        $tableName      = $table->getName();
        $condition      = $this->_addAuthorizationCondition($condition);
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $sql            = 'SELECT COUNT(*) FROM "' . $tableName . '"' . $whereClause;
        $stmt           = $this->query($sql, $parameters);
        return $stmt->fetchColumn(0);
    }

    public function selectAllUnordered(ReflectedTable $table = null, array $columnNames = [], Condition $condition = null)    {
        $parameters     = [];
        $selectColumns  = $this->_columns->getSelect($table, $columnNames);
        $tableName      = $table->getName();
        $condition      = $this->_addAuthorizationCondition($condition);
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $sql            = 'SELECT ' . $selectColumns . ' FROM "' . $tableName . '"' . $whereClause;
        $stmt           = $this->query($sql, $parameters);
        $records        = $stmt->fetchAll();
        $this->_converter->convertRecords($table, $columnNames, $records);
        return $records;
    }

    public function selectAll(ReflectedTable $table = null, array $columnNames = [], Condition $condition = null, array $columnOrdering = [], $offset = null, $limit = null) {
        $parameters     = [];
        if ($limit == 0) {
            return [];
        }
        $selectColumns  = $this->_columns->getSelect($table, $columnNames);
        $tableName      = $table->getName();
        $condition      = $this->_addAuthorizationCondition($condition);
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $orderBy        = $this->_columns->getOrderBy($table, $columnOrdering);
        $offsetLimit    = $this->_columns->getOffsetLimit($offset, $limit);
        $sql            = 'SELECT ' . $selectColumns . ' FROM "' . $tableName . '"' . $whereClause . ' ORDER BY ' . $orderBy . ' ' . $offsetLimit;
        $stmt           = $this->query($sql, $parameters);
        $records        = $stmt->fetchAll();
        $this->_converter->convertRecords($table, $columnNames, $records);
        return $records;
    }

    public function updateSingle(ReflectedTable $table = null, array $columnValues = [], $id = null)  {
        if (count($columnValues) == 0) {
            return 0;
        }
        $this->_converter->convertColumnValues($table, $columnValues);
        $updateColumns  = $this->_columns->getUpdate($table, $columnValues);
        $tableName      = $table->getName();
        $condition      = new ColumnCondition($table->getPk(), 'eq', $id);
        $condition      = $this->_addAuthorizationCondition($condition);
        $parameters     = array_values($columnValues);
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $sql            = 'UPDATE "' . $tableName . '" SET ' . $updateColumns . $whereClause;
        $stmt           = $this->query($sql, $parameters);
        return $stmt->rowCount();
    }

    public function deleteSingle(ReflectedTable $table = null,$id = null)  {
        $tableName      = $table->getName();
        $condition      = new ColumnCondition($table->getPk(), 'eq', $id);
        $condition      = $this->_addAuthorizationCondition($condition);
        $parameters     = array();
        $whereClause    = $this->_conditions->getWhereClause($condition, $parameters);
        $sql = 'DELETE FROM "' . $tableName . '" ' . $whereClause;
        $stmt = $this->query($sql, $parameters);
        return $stmt->rowCount();
    }

    public function query($sql = null, array $parameters = []) {
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute($parameters);
        return $stmt;
    }

}