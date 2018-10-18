<?php
namespace Evie\Rest\System\Database;

class GenericReflection {

    const DEFAULT_DRIVER    = 'mysql';
    const PG_SQL_DRIVER     = 'pg_sql';
    const SQL_SRV_DRIVER    = 'sql_srv';

    private $_pdo           = null;
    private $_driver        = null;
    private $_database      = null;
    private $_typeConverter = null;

    private function _getTablesSql()    {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return 'SELECT c.relname as "TABLE_NAME" FROM pg_catalog.pg_class c LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace WHERE c.relkind IN (\'r\') AND n.nspname <> \'pg_catalog\' AND n.nspname <> \'information_schema\' AND n.nspname !~ \'^pg_toast\' AND pg_catalog.pg_table_is_visible(c.oid) AND \'\' <> ? ORDER BY "TABLE_NAME";';
            case self::SQL_SRV_DRIVER:
                return 'SELECT o.name as "TABLE_NAME" FROM sysobjects o WHERE o.xtype = \'U\' ORDER BY "TABLE_NAME"';
            default:
                return 'SELECT "TABLE_NAME" FROM "INFORMATION_SCHEMA"."TABLES" WHERE "TABLE_TYPE" IN (\'BASE TABLE\') AND "TABLE_SCHEMA" = ? ORDER BY BINARY "TABLE_NAME"';
        }
    }

    private function _getTableColumnsSql()  {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return 'SELECT a.attname AS "COLUMN_NAME", case when a.attnotnull then \'NO\' else \'YES\' end as "IS_NULLABLE", pg_catalog.format_type(a.atttypid, -1) as "DATA_TYPE", case when a.atttypmod < 0 then NULL else a.atttypmod-4 end as "CHARACTER_MAXIMUM_LENGTH", case when a.atttypid != 1700 then NULL else ((a.atttypmod - 4) >> 16) & 65535 end as "NUMERIC_PRECISION", case when a.atttypid != 1700 then NULL else (a.atttypmod - 4) & 65535 end as "NUMERIC_SCALE" FROM pg_attribute a JOIN pg_class pgc ON pgc.oid = a.attrelid WHERE pgc.relname = ? AND \'\' <> ? AND a.attnum > 0 AND NOT a.attisdropped;';
            case self::SQL_SRV_DRIVER:
                return 'SELECT c.name AS "COLUMN_NAME", c.is_nullable AS "IS_NULLABLE", t.Name AS "DATA_TYPE", (c.max_length/2) AS "CHARACTER_MAXIMUM_LENGTH", c.precision AS "NUMERIC_PRECISION", c.scale AS "NUMERIC_SCALE" FROM sys.columns c INNER JOIN sys.types t ON c.user_type_id = t.user_type_id WHERE c.object_id = OBJECT_ID(?) AND \'\' <> ?';
            default:
                return 'SELECT "COLUMN_NAME", "IS_NULLABLE", "DATA_TYPE", "CHARACTER_MAXIMUM_LENGTH", "NUMERIC_PRECISION", "NUMERIC_SCALE" FROM "INFORMATION_SCHEMA"."COLUMNS" WHERE "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ?';
        }
    }

    private function _getTablePrimaryKeysSQL()   {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return 'SELECT a.attname AS "COLUMN_NAME" FROM pg_attribute a JOIN pg_constraint c ON (c.conrelid, c.conkey[1]) = (a.attrelid, a.attnum) JOIN pg_class pgc ON pgc.oid = a.attrelid WHERE pgc.relname = ? AND \'\' <> ? AND c.contype = \'p\'';
            case self::SQL_SRV_DRIVER:
                return 'SELECT c.NAME as "COLUMN_NAME" FROM sys.key_constraints kc inner join sys.objects t on t.object_id = kc.parent_object_id INNER JOIN sys.index_columns ic ON kc.parent_object_id = ic.object_id and kc.unique_index_id = ic.index_id INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id WHERE kc.type = \'PK\' and t.object_id = OBJECT_ID(?) and \'\' <> ?';
            default:
                return 'SELECT "COLUMN_NAME" FROM "INFORMATION_SCHEMA"."KEY_COLUMN_USAGE" WHERE "CONSTRAINT_NAME" = \'PRIMARY\' AND "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ?';
        }
    }

    private function _getTableForeignKeysSQL()   {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                return 'SELECT a.attname AS "COLUMN_NAME", c.confrelid::regclass::text AS "REFERENCED_TABLE_NAME" FROM pg_attribute a JOIN pg_constraint c ON (c.conrelid, c.conkey[1]) = (a.attrelid, a.attnum) JOIN pg_class pgc ON pgc.oid = a.attrelid WHERE pgc.relname = ? AND \'\' <> ? AND c.contype  = \'f\'';
            case self::SQL_SRV_DRIVER:
                return 'SELECT COL_NAME(fc.parent_object_id, fc.parent_column_id) AS "COLUMN_NAME", OBJECT_NAME (f.referenced_object_id) AS "REFERENCED_TABLE_NAME" FROM sys.foreign_keys AS f INNER JOIN sys.foreign_key_columns AS fc ON f.OBJECT_ID = fc.constraint_object_id WHERE f.parent_object_id = OBJECT_ID(?) and \'\' <> ?';
            default:
                return 'SELECT "COLUMN_NAME", "REFERENCED_TABLE_NAME" FROM "INFORMATION_SCHEMA"."KEY_COLUMN_USAGE" WHERE "REFERENCED_TABLE_NAME" IS NOT NULL AND "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ?';
        }
    }

    private function _query($sql = null, array $parameters = [])   {
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    public function __construct(\PDO $pdo = null, $driver = null, $database = null)   {
        $this->_pdo = $pdo;
        $this->_driver = $driver;
        $this->_database = $database;
        $this->_typeConverter = new TypeConverter($driver);
    }

    public function getIgnoredTables()  {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:  return ['spatial_ref_sys'];
            case self::SQL_SRV_DRIVER: return [];
            default: return [];
        }
    }

    public function getDatabaseName()   {
        return $this->_database;
    }

    public function getTables() {
        return $this->_query($this->_getTablesSql(), [$this->_database]);
    }

    public function getTableColumns($tableName = null)   {
        return $this->_query($this->_getTableColumnsSql(), [$tableName, $this->_database]);
    }

    public function getTablePrimaryKeys($tableName = null)   {
        $primaryKeys = [];
        $sql = $this->_getTablePrimaryKeysSQL();
        $results = $this->_query($sql, [$tableName, $this->_database]);
        foreach ($results as $result) {
            $primaryKeys[] = $result['COLUMN_NAME'];
        }
        return $primaryKeys;
    }

    public function getTableForeignKeys($tableName = null)   {
        $sql = $this->_getTableForeignKeysSQL();
        $results = $this->_query($sql, [$tableName, $this->_database]);
        $foreignKeys = [];
        foreach ($results as $result) {
            $foreignKeys[$result['COLUMN_NAME']] = $result['REFERENCED_TABLE_NAME'];
        }
        return $foreignKeys;
    }

    public function toJdbcType($type = null, $size = null)    {
        return $this->_typeConverter->toJdbc($type, $size);
    }

}