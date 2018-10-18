<?php
namespace Evie\Rest\System\Database;

use Evie\Rest\System\Column\Reflection\ReflectedColumn;
use Evie\Rest\System\Column\Reflection\ReflectedTable;

class GenericDefinition {

    const DEFAULT_DRIVER    = 'mysql';
    const PG_SQL_DRIVER     = 'pg_sql';
    const SQL_SRV_DRIVER    = 'sql_srv';
    const PG_SQL_RETURN     = 'serial';

    private $_pdo           = null;
    private $_driver        = null;
    private $_database      = null;
    private $_typeConverter = null;
    private $_reflection    = null;

    private function _quote($identifier = null)   {
        return '"' . str_replace('"', '', $identifier) . '"';
    }

    private function _getPrimaryKey($tableName = null)   {
        $pks = $this->_reflection->getTablePrimaryKeys($tableName);
        if (count($pks) == 1) {
            return $pks[0];
        }
        return "";
    }

    private function _canAutoIncrement(ReflectedColumn $column = null)    {
        return in_array($column->_getType(), ['integer', 'bigint']);
    }

    private function _getColumnAutoIncrement(ReflectedColumn $column = null, $update = false)  {
        if (!$this->_canAutoIncrement($column)) {
            return '';
        }
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return '';
            case self::SQL_SRV_DRIVER:
                return ($column->getPk() && !$update) ? ' IDENTITY(1,1)' : '';
            default:
                return $column->getPk() ? ' AUTO_INCREMENT' : '';
        }
    }

    private function _getColumnNullType(ReflectedColumn $column = null, $update = false)   {
        if ($this->_driver == self::PG_SQL_DRIVER && $update) {
            return '';
        }
        return $column->_getNullable() ? ' NULL' : ' NOT NULL';
    }

    private function _getTableRenameSql($tableName = null, $newTableName = null)   {
        $first = $this->_quote($tableName);
        $last = $this->_quote($newTableName);

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return "ALTER TABLE $first RENAME TO $last";
            case self::SQL_SRV_DRIVER:
                return "EXEC sp_rename $first, $last";
            default:
                return "RENAME TABLE $first TO $last";
        }
    }

    private function _getColumnRenameSql($tableName = null, $columnName = null, ReflectedColumn $newColumn = null)  {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $third  = $this->_quote($newColumn->getName());

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return "ALTER TABLE $first RENAME COLUMN $second TO $third";
            case self::SQL_SRV_DRIVER:
                $last = $this->_quote($tableName . '.' . $columnName);
                return "EXEC sp_rename $last, $second, 'COLUMN'";
            default:
                $last = $this->getColumnType($newColumn, true);
                return "ALTER TABLE $first CHANGE $second $third $last";
        }
    }

    private function _getColumnRetypeSql($tableName = null, $columnName = null, ReflectedTable $newColumn = null) {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $third  = $this->_quote($newColumn->getName());
        $last   = $this->getColumnType($newColumn, true);

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return "ALTER TABLE $first ALTER COLUMN $third TYPE $last";
            case self::SQL_SRV_DRIVER:
                return "ALTER TABLE $first ALTER COLUMN $third $last";
            default:
                return "ALTER TABLE $first CHANGE $second $third $last";
        }
    }

    private function _getSetColumnNullSql($tableName = null, $columnName = null, ReflectedTable $newColumn = null) {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $third  = $this->_quote($newColumn->getName());
        $fourth = $this->getColumnType($newColumn, true);

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                $last = $newColumn->getNullable() ? 'DROP NOT NULL' : 'SET NOT NULL';
                return "ALTER TABLE $first ALTER COLUMN $second $last";
            case self::SQL_SRV_DRIVER:
                return "ALTER TABLE $first ALTER COLUMN $second $fourth";
            default:
                return "ALTER TABLE $first CHANGE $second $third $fourth";
        }
    }

    private function _getSetColumnPkConstraintSql($tableName = null, $columnName = null, ReflectedTable $newColumn = null)  {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $third  = $this->_quote($tableName . '_pkey');

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
            case self::SQL_SRV_DRIVER:
                $last = $newColumn->getPk() ? "ADD PRIMARY KEY ($second)" : "DROP CONSTRAINT $third";
                return "ALTER TABLE $first $last";
            default:
                $last = $newColumn->getPk() ? "ADD PRIMARY KEY ($second)" : 'DROP PRIMARY KEY';
                return "ALTER TABLE $first $last";
        }
    }

    private function _getSetColumnPkSequenceSql($tableName = null, $columnName = null, ReflectedTable $newColumn = null)    {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $last   = $this->_quote($tableName . '_' . $columnName . '_seq');

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return $newColumn->getPk() ? "CREATE SEQUENCE $last OWNED BY $first.$second" : "DROP SEQUENCE $last";
            case self::SQL_SRV_DRIVER:
                return $newColumn->getPk() ? "CREATE SEQUENCE $last" : "DROP SEQUENCE $last";
            default:
                return "select 1";
        }
    }

    private function _getSetColumnPkSequenceStartSql($tableName = null, $columnName = null)   {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $last   = $this->_pdo->quote($tableName . '_' . $columnName . '_seq');

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return "SELECT setval($last, (SELECT max($second)+1 FROM $first));";
            case self::SQL_SRV_DRIVER:
                return "ALTER SEQUENCE $last RESTART WITH (SELECT max($second)+1 FROM $first)";
            default:
                return "select 1";
        }
    }

    private function _getSetColumnPkDefaultSQL($tableName = null, $columnName = null, ReflectedTable $newColumn = null) {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                if ($newColumn->getPk()) {
                    $third = $this->_pdo->quote($tableName . '_' . $columnName . '_seq');
                    $last = "SET DEFAULT nextval($third)";
                } else {
                    $last = 'DROP DEFAULT';
                }
                return "ALTER TABLE $first ALTER COLUMN $second $last";
            case self::SQL_SRV_DRIVER:
                $third = $this->_pdo->quote($tableName . '_' . $columnName . '_seq');
                $last = $this->_quote('DF_' . $tableName . '_' . $columnName);
                if ($newColumn->getPk()) {
                    return "ALTER TABLE $first ADD CONSTRAINT $last DEFAULT NEXT VALUE FOR $third FOR $second";
                } else {
                    return "ALTER TABLE $first DROP CONSTRAINT $last";
                }
            default:
                $third = $this->_quote($newColumn->getName());
                $last = $this->getColumnType($newColumn, true);
                return "ALTER TABLE $first CHANGE $second $third $last";
        }
    }

    private function _getAddColumnFkConstraintSQL($tableName = null, $columnName = null, ReflectedTable $newColumn = null)  {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($columnName);
        $third  = $this->_quote($tableName . '_' . $columnName . '_fkey');
        $fourth = $this->_quote($newColumn->getFk());
        $last   = $this->_quote($this->_getPrimaryKey($newColumn->getFk()));

        return "ALTER TABLE $first ADD CONSTRAINT $third FOREIGN KEY ($second) REFERENCES $fourth ($last)";
    }

    private function _getRemoveColumnFkConstraintSQL($tableName = null, $columnName = null)  {
        $first  = $this->_quote($tableName);
        $last   = $this->_quote($tableName . '_' . $columnName . '_fkey');

        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
            case self::SQL_SRV_DRIVER:
                return "ALTER TABLE $first DROP CONSTRAINT $last";
            default:
                return "ALTER TABLE $first DROP FOREIGN KEY $last";
        }
    }

    private function _getAddTableSQL(ReflectedTable $newTable)  {
        $fields         = [];
        $constraints    = [];
        $tableName      = $newTable->getName();
        $first          = $this->_quote($tableName);
        foreach ($newTable->columnNames() as $columnName) {
            $newColumn = $newTable->get($columnName);
            $f1 = $this->_quote($columnName);
            $f2 = $this->getColumnType($newColumn, false);
            $f3 = $this->_quote($tableName . '_' . $columnName . '_fkey');
            $f4 = $this->_quote($newColumn->getFk());
            $f5 = $this->_quote($this->_getPrimaryKey($newColumn->getFk()));
            $fields[] = "$f1 $f2";
            if ($newColumn->getPk()) {
                $constraints[] = "PRIMARY KEY ($f1)";
            }
            if ($newColumn->getFk()) {
                $constraints[] = "CONSTRAINT $f3 FOREIGN KEY ($f1) REFERENCES $f4 ($f5)";
            }
        }
        $last = implode(',', array_merge($fields, $constraints));

        return "CREATE TABLE $first ($last);";
    }

    private function _getAddColumnSQL($tableName = null, ReflectedColumn $newColumn)    {
        $first  = $this->_quote($tableName);
        $second = $this->_quote($newColumn->getName());
        $last   = $this->getColumnType($newColumn, false);

        return "ALTER TABLE $first ADD COLUMN $second $last";
    }

    private function _getRemoveTableSQL($tableName = null)  {
        $last = $this->_quote($tableName);
        return "DROP TABLE $last CASCADE;";
    }

    private function _getRemoveColumnSQL($tableName = null, $columnName = null) {
        $first = $this->_quote($tableName);
        $last = $this->_quote($columnName);
        return "ALTER TABLE $first DROP COLUMN $last CASCADE;";
    }

    private function _query($sql = null)    {
        $stmt = $this->_pdo->prepare($sql);
        return $stmt->execute();
    }

    public function __construct(\PDO $pdo = null, $driver = null, $database = null)   {
        $this->_pdo = $pdo;
        $this->_driver = $driver;
        $this->_database = $database;
        $this->_typeConverter = new TypeConverter($driver);
        $this->_reflection = new GenericReflection($pdo, $driver, $database);
    }

    public function getColumnType(ReflectedColumn $column = null, $update = false) {
        if ($this->driver == self::PG_SQL_DRIVER && !$update && $column->getPk() && $this->_canAutoIncrement($column)) {
            return self::PG_SQL_RETURN;
        }
        $type = $this->_typeConverter->fromJdbc($column->getType(), $column->getPk());
        if ($column->hasPrecision() && $column->hasScale()) {
            $size = '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        } else if ($column->hasPrecision()) {
            $size = '(' . $column->getPrecision() . ')';
        } else if ($column->hasLength()) {
            $size = '(' . $column->getLength() . ')';
        } else {
            $size = '';
        }
        $null = $this->_getColumnNullType($column, $update);
        $auto = $this->_getColumnAutoIncrement($column, $update);
        return $type . $size . $null . $auto;
    }

    public function renameTable($tableName = null, $newTableName = null)    {
        $sql = $this->_getTableRenameSQL($tableName, $newTableName);
        return $this->_query($sql);
    }

    public function renameColumn($tableName = null, $columnName = null, ReflectedTable $newColumn = null)   {
        $sql = $this->_getColumnRenameSQL($tableName, $columnName, $newColumn);
        return $this->_query($sql);
    }

    public function retypeColumn($tableName = null, $columnName = null, ReflectedTable $newColumn = null)   {
        $sql = $this->_getColumnRetypeSQL($tableName, $columnName, $newColumn);
        return $this->_query($sql);
    }

    public function setColumnNull($tableName = null, $columnName = null, ReflectedTable $newColumn = null)  {
        $sql = $this->_getSetColumnNullableSQL($tableName, $columnName, $newColumn);
        return $this->_query($sql);
    }

    public function addColumnPrimaryKey($tableName = null, $columnName = null, ReflectedTable $newColumn = null)    {
        $sql = $this->_getSetColumnPkConstraintSQL($tableName, $columnName, $newColumn);
        $this->_query($sql);
        if ($this->_canAutoIncrement($newColumn)) {
            $sql = $this->_getSetColumnPkSequenceSQL($tableName, $columnName, $newColumn);
            $this->_query($sql);
            $sql = $this->_getSetColumnPkSequenceStartSQL($tableName, $columnName, $newColumn);
            $this->_query($sql);
            $sql = $this->_getSetColumnPkDefaultSQL($tableName, $columnName, $newColumn);
            $this->_query($sql);
        }
        return true;
    }

    public function removeColumnPrimaryKey($tableName = null, $columnName = null, ReflectedTable $newColumn = null) {
        if ($this->_canAutoIncrement($newColumn)) {
            $sql = $this->_getSetColumnPkDefaultSQL($tableName, $columnName, $newColumn);
            $this->_query($sql);
            $sql = $this->_getSetColumnPkSequenceSQL($tableName, $columnName, $newColumn);
            $this->_query($sql);
        }
        $sql = $this->_getSetColumnPkConstraintSQL($tableName, $columnName, $newColumn);
        $this->_query($sql);
        return true;
    }

    public function addColumnForeignKey($tableName = null, $columnName = null, ReflectedTable $newColumn = null)    {
        $sql = $this->_getAddColumnFkConstraintSQL($tableName, $columnName, $newColumn);
        return $this->_query($sql);
    }

    public function removeColumnForeignKey($tableName = null, $columnName = null, ReflectedTable $newColumn = null) {
        $sql = $this->_getRemoveColumnFkConstraintSQL($tableName, $columnName, $newColumn);
        return $this->_query($sql);
    }

    public function addTable(ReflectedTable $newTable)  {
        $sql = $this->_getAddTableSQL($newTable);
        return $this->_query($sql);
    }

    public function addColumn($tableName = null, ReflectedColumn $newColumn)    {
        $sql = $this->_getAddColumnSQL($tableName, $newColumn);
        return $this->_query($sql);
    }

    public function removeTable($tableName = null)  {
        $sql = $this->_getRemoveTableSQL($tableName);
        return $this->_query($sql);
    }

    public function removeColumn($tableName = null, $columnName = null)
    {
        $sql = $this->_getRemoveColumnSQL($tableName, $columnName);
        return $this->_query($sql);
    }

}