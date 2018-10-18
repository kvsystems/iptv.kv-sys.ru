<?php
namespace Evie\Rest\System\Column\Reflection;

use Evie\Rest\System\Database\GenericReflection;

class ReflectedColumn implements \JsonSerializable  {

    const DEFAULT_LENGTH    = 255;
    const DEFAULT_PRECISION = 19;
    const DEFAULT_SCALE     = 4;

    private $_name      = null;
    private $_type      = null;
    private $_length    = null;
    private $_precision = null;
    private $_scale     = null;
    private $_null      = null;
    private $_pk        = null;
    private $_fk        = null;

    private function _sanitize()    {
        $this->_length = $this->hasLength() ? $this->getLength() : 0;
        $this->_precision = $this->hasPrecision() ? $this->getPrecision() : 0;
        $this->_scale = $this->hasScale() ? $this->getScale() : 0;
    }

    public function __construct($name = null, $type = null, $length = 0, $precision = 0, $scale = 0, $null = false,
                                $pk = false, $fk = null)    {
        $this->_name        = $name;
        $this->_type        = $type;
        $this->_length      = $length;
        $this->_precision   = $precision;
        $this->_scale       = $scale;
        $this->_null        = $null;
        $this->_pk          = $pk;
        $this->_fk          = $fk;

        $this->_sanitize();
    }

    public static function fromReflection(GenericReflection $reflection = null, array $columnResult = [])   {
        $name = $columnResult['COLUMN_NAME'];
        $length = (int) $columnResult['CHARACTER_MAXIMUM_LENGTH'];
        $type = $reflection->toJdbcType($columnResult['DATA_TYPE'], $length);
        $precision = (int) $columnResult['NUMERIC_PRECISION'];
        $scale = (int) $columnResult['NUMERIC_SCALE'];
        $nullable = in_array(strtoupper($columnResult['IS_NULLABLE']), ['TRUE', 'YES', 'T', 'Y', '1']);
        $pk = false;
        $fk = '';
        return new ReflectedColumn($name, $type, $length, $precision, $scale, $nullable, $pk, $fk);
    }

    public static function fromJson($json) {
        $name = $json->name;
        $type = $json->type;
        $length = isset($json->length) ? $json->length : 0;
        $precision = isset($json->precision) ? $json->precision : 0;
        $scale = isset($json->scale) ? $json->scale : 0;
        $nullable = isset($json->nullable) ? $json->nullable : false;
        $pk = isset($json->pk) ? $json->pk : false;
        $fk = isset($json->fk) ? $json->fk : '';
        return new ReflectedColumn($name, $type, $length, $precision, $scale, $nullable, $pk, $fk);
    }

    public function getName()   {
        return $this->_name;
    }

    public function getNull()   {
        return $this->_null;
    }

    public function getType()   {
        return $this->_type;
    }

    public function getLength() {
        return $this->_length ?: self::DEFAULT_LENGTH;
    }

    public function getPrecision()  {
        return $this->_precision ?: self::DEFAULT_PRECISION;
    }

    public function getScale()  {
        return $this->_scale ?: self::DEFAULT_SCALE;
    }

    public function hasLength() {
        return in_array($this->_type, ['varchar', 'varbinary']);
    }

    public function hasPrecision()  {
        return $this->_type == 'decimal';
    }

    public function hasScale()  {
        return $this->_type == 'decimal';
    }

    public function isBinary()  {
        return in_array($this->_type, ['blob', 'varbinary']);
    }

    public function isBoolean() {
        return $this->_type == 'boolean';
    }

    public function isGeometry()    {
        return $this->_type == 'geometry';
    }

    public function setPk($value)   {
        $this->_pk = $value;
    }

    public function getPk() {
        return $this->_pk;
    }

    public function setFk($value = null)   {
        $this->_fk = $value;
    }

    public function getFk() {
        return $this->_fk;
    }

    public function serialize() {
        return [
            'name'      => $this->_name,
            'type'      => $this->_type,
            'length'    => $this->_length,
            'precision' => $this->_precision,
            'scale'     => $this->_scale,
            'null'      => $this->_null,
            'pk'        => $this->_pk,
            'fk'        => $this->_fk
        ];
    }

    public function jsonSerialize() {
        return array_filter($this->serialize());
    }

}