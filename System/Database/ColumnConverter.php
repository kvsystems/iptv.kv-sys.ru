<?php
namespace Evie\Rest\System\Database;

use Evie\Rest\System\Column\Reflection\ReflectedColumn;

class ColumnConverter   {

    const DEFAULT_DRIVER    = 'mysql';
    const PG_SQL_DRIVER     = 'pg_sql';
    const SQL_SRV_DRIVER    = 'sql_srv';

    private $_driver = null;

    public function __construct($driver = null) {
        $this->_driver = $driver;
    }

    public function convertColumnValue(ReflectedColumn $column) {
        if ($column->isBinary()) {
            switch ($this->_driver) {
                case self::PG_SQL_DRIVER:
                    return "decode(?, 'base64')";
                case self::SQL_SRV_DRIVER:
                    return "CONVERT(XML, ?).value('.','varbinary(max)')";
                default:
                    return "FROM_BASE64(?)";
            }
        }
        if ($column->isGeometry()) {
            switch ($this->_driver) {
                case self::PG_SQL_DRIVER:
                    return "ST_GeomFromText(?)";
                case self::SQL_SRV_DRIVER:
                    return "geometry::STGeomFromText(?,0)";
                default:
            }
        }
        return '?';
    }

    public function convertColumnName(ReflectedColumn $column, $value = null)   {
        if ($column->isBinary()) {
            switch ($this->_driver) {
                case self::PG_SQL_DRIVER:
                    return "encode($value::bytea, 'base64') as $value";
                case self::SQL_SRV_DRIVER:
                    return "CAST(N'' AS XML).value('xs:base64Binary(xs:hexBinary(sql:column($value)))', 'VARCHAR(MAX)') as $value";
                default:
                    return "TO_BASE64($value) as $value";

            }
        }
        if ($column->isGeometry()) {
            switch ($this->_driver) {
                case self::PG_SQL_DRIVER:
                    return "ST_AsText($value) as $value";
                case self::SQL_SRV_DRIVER:
                    return "REPLACE($value.STAsText(),' (','(') as $value";
                default:
            }
        }
        return $value;
    }

}