<?php
namespace Evie\Rest\System\Column\Reflection;

use Evie\Rest\System\Database\GenericReflection;

class ReflectedTable implements \JsonSerializable   {

    private $_name      = null;
    private $_columns   = [];
    private $_pk        = null;
    private $_fks       = [];

    public function __construct($name = null, array $columns = [])  {
        $this->_name = $name;

        foreach ($columns as $column) {
            $columnName = $column->getName();
            $this->_columns[$columnName] = $column;
        }

        $this->pk = null;
        foreach ($columns as $column) {
            if ($column->getPk() == true) {
                $this->_pk = $column;
            }
        }

        foreach ($columns as $column) {
            $columnName = $column->getName();
            $referencedTableName = $column->getFk();
            if ($referencedTableName != '') {
                $this->_fks[$columnName] = $referencedTableName;
            }
        }
    }

    public static function fromReflection(GenericReflection $reflection = null, $name = null)   {
        $columns = [];
        foreach ($reflection->getTableColumns($name) as $tableColumn) {
            $column = ReflectedColumn::fromReflection($reflection, $tableColumn);
            $columns[$column->getName()] = $column;
        }

        $columnNames = $reflection->getTablePrimaryKeys($name);
        if (count($columnNames) == 1) {
            $columnName = $columnNames[0];
            if (isset($columns[$columnName])) {
                $pk = $columns[$columnName];
                $pk->setPk(true);
            }
        }

        $fks = $reflection->getTableForeignKeys($name);
        foreach ($fks as $columnName => $table) {
            $columns[$columnName]->setFk($table);
        }
        return new ReflectedTable($name, array_values($columns));
    }

    public static function fromJson($json = null)   {
        $name = $json->name;
        $columns = [];
        if (isset($json->columns) && is_array($json->columns)) {
            foreach ($json->columns as $column) {
                $columns[] = ReflectedColumn::fromJson($column);
            }
        }
        return new ReflectedTable($name, $columns);
    }

    public function exists($columnName = null)  {
        return isset($this->_columns[$columnName]);
    }

    public function hasPk() {
        return $this->_pk != null;
    }

    public function getPk() {
        return $this->_pk;
    }

    public function getName()   {
        return $this->_name;
    }

    public function columnNames()   {
        return array_keys($this->_columns);
    }

    public function get($columnName)    {
        return $this->_columns[$columnName];
    }

    public function getFksTo($tableName = null) {
        $columns = array();
        foreach ($this->_fks as $columnName => $referencedTableName) {
            if ($tableName == $referencedTableName) {
                $columns[] = $this->_columns[$columnName];
            }
        }
        return $columns;
    }

    public function removeColumn($columnName = null)    {
        if (!isset($this->_columns[$columnName])) {
            return false;
        }
        unset($this->_columns[$columnName]);
        return true;
    }

    public function serialize() {
        return [
            'name' => $this->_name,
            'columns' => array_values($this->_columns),
        ];
    }

    public function jsonSerialize() {
        return $this->serialize();
    }

}


