<?php
namespace Evie\Rest\System\Database;

class TypeConverter {

    private $_driver = null;

    private $_fromJdbc = [
        'mysql' => [
            'clob' => 'longtext',
            'boolean' => 'bit',
            'blob' => 'longblob',
            'timestamp' => 'datetime',
        ],
        'pgsql' => [
            'clob' => 'text',
            'blob' => 'bytea',
        ],
        'sqlsrv' => [
            'boolean' => 'bit',
        ],
    ];

    private $_toJdbc = [
        'simplified' => [
            'char' => 'varchar',
            'longvarchar' => 'clob',
            'nchar' => 'varchar',
            'nvarchar' => 'varchar',
            'longnvarchar' => 'clob',
            'binary' => 'varbinary',
            'longvarbinary' => 'blob',
            'tinyint' => 'integer',
            'smallint' => 'integer',
            'real' => 'float',
            'numeric' => 'decimal',
            'time_with_timezone' => 'time',
            'timestamp_with_timezone' => 'timestamp',
        ],
        'mysql' => [
            'tinyint(1)' => 'boolean',
            'bit(0)' => 'boolean',
            'bit(1)' => 'boolean',
            'tinyblob' => 'blob',
            'mediumblob' => 'blob',
            'longblob' => 'blob',
            'tinytext' => 'clob',
            'mediumtext' => 'clob',
            'longtext' => 'clob',
            'text' => 'clob',
            'int' => 'integer',
            'polygon' => 'geometry',
            'point' => 'geometry',
            'datetime' => 'timestamp',
        ],
        'pgsql' => [
            'bigserial' => 'bigint',
            'bit varying' => 'bit',
            'box' => 'geometry',
            'bytea' => 'blob',
            'character varying' => 'varchar',
            'character' => 'char',
            'cidr' => 'varchar',
            'circle' => 'geometry',
            'double precision' => 'double',
            'inet' => 'integer',
            //'interval [ fields ]'
            'jsonb' => 'clob',
            'line' => 'geometry',
            'lseg' => 'geometry',
            'macaddr' => 'varchar',
            'money' => 'decimal',
            'path' => 'geometry',
            'point' => 'geometry',
            'polygon' => 'geometry',
            'real' => 'float',
            'serial' => 'integer',
            'text' => 'clob',
            'time without time zone' => 'time',
            'time with time zone' => 'time_with_timezone',
            'timestamp without time zone' => 'timestamp',
            'timestamp with time zone' => 'timestamp_with_timezone',
            //'tsquery'=
            //'tsvector'
            //'txid_snapshot'
            'uuid' => 'char',
            'xml' => 'clob',
        ],
        'sqlsrv' => [
            'varbinary(0)' => 'blob',
            'bit' => 'boolean',
            'datetime' => 'timestamp',
            'datetime2' => 'timestamp',
            'float' => 'double',
            'image' => 'longvarbinary',
            'int' => 'integer',
            'money' => 'decimal',
            'ntext' => 'longnvarchar',
            'smalldatetime' => 'timestamp',
            'smallmoney' => 'decimal',
            'text' => 'longvarchar',
            'timestamp' => 'binary',
            'tinyint' => 'tinyint',
            'udt' => 'varbinary',
            'uniqueidentifier' => 'char',
            'xml' => 'longnvarchar',
        ],
    ];

    private $_valid = [
        //'array' => true,
        'bigint' => true,
        'binary' => true,
        'bit' => true,
        'blob' => true,
        'boolean' => true,
        'char' => true,
        'clob' => true,
        //'datalink' => true,
        'date' => true,
        'decimal' => true,
        'distinct' => true,
        'double' => true,
        'float' => true,
        'integer' => true,
        //'java_object' => true,
        'longnvarchar' => true,
        'longvarbinary' => true,
        'longvarchar' => true,
        'nchar' => true,
        'nclob' => true,
        //'null' => true,
        'numeric' => true,
        'nvarchar' => true,
        //'other' => true,
        'real' => true,
        //'ref' => true,
        //'ref_cursor' => true,
        //'rowid' => true,
        'smallint' => true,
        //'sqlxml' => true,
        //'struct' => true,
        'time' => true,
        'time_with_timezone' => true,
        'timestamp' => true,
        'timestamp_with_timezone' => true,
        'tinyint' => true,
        'varbinary' => true,
        'varchar' => true,
        // extra:
        'geometry' => true,
    ];

    public function __construct($driver = null)   {
        $this->_driver = $driver;
    }

    public function toJdbc($type = null, $size = null)    {
        $jdbcType = strtolower($type);
        if (isset($this->_toJdbc[$this->_driver]["$jdbcType($size)"])) {
            $jdbcType = $this->_toJdbc[$this->_driver]["$jdbcType($size)"];
        }
        if (isset($this->_toJdbc[$this->_driver][$jdbcType])) {
            $jdbcType = $this->_toJdbc[$this->_driver][$jdbcType];
        }
        if (isset($this->_toJdbc['simplified'][$jdbcType])) {
            $jdbcType = $this->_toJdbc['simplified'][$jdbcType];
        }
        if (!isset($this->_valid[$jdbcType])) {
            throw new \Exception("Unsupported type '$jdbcType' for driver '$this->_driver'");
        }
        return $jdbcType;
    }

    public function fromJdbc($type = null)  {
        $jdbcType = strtolower($type);
        if (isset($this->_fromJdbc[$this->_driver][$jdbcType])) {
            $jdbcType = $this->_fromJdbc[$this->_driver][$jdbcType];
        }
        return $jdbcType;
    }

}