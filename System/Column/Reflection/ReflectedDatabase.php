<?php
namespace Evie\Rest\System\Column\Reflection;

use Evie\Rest\System\Database\GenericReflection;

class ReflectedDatabase implements \JsonSerializable    {

    private $_name       = null;
    private $_tableNames = null;

    public function __construct($name = null, array $tableNames = [])
    {
        $this->_name = $name;
        $this->_tableNames = [];
        foreach ($tableNames as $tableName) {
            $this->_tableNames[$tableName] = true;
        }
    }

    public static function fromReflection(GenericReflection $reflection = null) {
        $tableNames = [];
        $name = $reflection->getDatabaseName();
        foreach ($reflection->getTables() as $table) {
            $tableName = $table['TABLE_NAME'];
            if (in_array($tableName, $reflection->getIgnoredTables())) {
                continue;
            }
            $tableNames[$tableName] = true;
        }
        return new ReflectedDatabase($name, array_keys($tableNames));
    }

    public static function fromJson($json)  {
        $name = $json->name;
        $tableNames = $json->tables;
        return new ReflectedDatabase($name, $tableNames);
    }

    public function getName()   {
        return $this->_name;
    }

    public function exists($tableName = null)   {
        return isset($this->_tableNames[$tableName]);
    }

    public function getTableNames() {
        return array_keys($this->_tableNames);
    }

    public function removeTable($tableName = null)  {
        if (!isset($this->_tableNames[$tableName])) {
            return false;
        }
        unset($this->_tableNames[$tableName]);
        return true;
    }

    public function serialize() {
        return [
            'name' => $this->_name,
            'tables' => array_keys($this->_tableNames),
        ];
    }

    public function jsonSerialize() {
        return $this->serialize();
    }
}