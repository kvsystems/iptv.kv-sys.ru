<?php
namespace Evie\Rest\System\Record;

use Evie\Rest\System\Column\Reflection\ReflectedTable;

class ColumnInclude    {

    private function _isMandatory($tableName = null, $columnName = null, array $params = [])  {
        return isset($params['mandatory']) && in_array($tableName . "." . $columnName, $params['mandatory']);
    }

    private function _select($tableName = null, $primaryTable = false, array $params = [],
                             $paramName = null, array $columnNames = [], $include = false)  {
        if (!isset($params[$paramName])) {
            return $columnNames;
        }
        $columns = array();
        foreach (explode(',', $params[$paramName][0]) as $columnName) {
            $columns[$columnName] = true;
        }
        $result = array();
        foreach ($columnNames as $columnName) {
            $match = isset($columns['*.*']);
            if (!$match) {
                $match = isset($columns[$tableName . '.*']) || isset($columns[$tableName . '.' . $columnName]);
            }
            if ($primaryTable && !$match) {
                $match = isset($columns['*']) || isset($columns[$columnName]);
            }
            if ($match) {
                if ($include || $this->_isMandatory($tableName, $columnName, $params)) {
                    $result[] = $columnName;
                }
            } else {
                if (!$include || $this->_isMandatory($tableName, $columnName, $params)) {
                    $result[] = $columnName;
                }
            }
        }
        return $result;
    }

    public function getNames(ReflectedTable $table = null, $primaryTable = null, array $params = [])    {
        $tableName = $table->getName();
        $results = $table->columnNames();
        $results = $this->_select($tableName, $primaryTable, $params, 'include', $results, true);
        $results = $this->_select($tableName, $primaryTable, $params, 'exclude', $results, false);
        return $results;
    }

    public function getValues(ReflectedTable $table = null, $primaryTable = false, $record = null, array $params = [])  {
        $results = array();
        $columnNames = $this->getNames($table, $primaryTable, $params);
        foreach ($columnNames as $columnName) {
            if (property_exists($record, $columnName)) {
                $results[$columnName] = $record->$columnName;
            }
        }
        return $results;
    }

}