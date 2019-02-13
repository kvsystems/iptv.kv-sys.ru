<?php
namespace Evie\Rest\System\Record;

use Evie\Rest\System\Column\ReflectionService;
use Evie\Rest\System\Column\Reflection\ReflectedTable;
use Evie\Rest\System\Database\GenericDB;
use Evie\Rest\System\Record\Condition\ColumnCondition;
use Evie\Rest\System\Record\Condition\OrCondition;
use Tqdev\PhpCrudApi\Record\ColumnIncluder;

class RelationJoiner    {

    private $_reflection    = null;
    private $_columns       = null;

    private function _getJoinsAsPathTree(array $params = [])    {
        $joins = new PathTree();
        if (isset($params['join'])) {
            foreach ($params['join'] as $tableNames) {
                $path = array();
                foreach (explode(',', $tableNames) as $tableName) {
                    $t = $this->_reflection->getTable($tableName);
                    if ($t != null) {
                        $path[] = $t->getName();
                    }
                }
                $joins->put($path, true);
            }
        }
        return $joins;
    }

    private function _hasAndBelongsToMany(ReflectedTable $t1 = null, ReflectedTable $t2 = null)   {
        foreach ($this->_reflection->getTableNames() as $tableName) {
            $t3 = $this->_reflection->getTable($tableName);
            if (count($t3->getFksTo($t1->getName())) > 0 && count($t3->getFksTo($t2->getName())) > 0) {
                return $t3;
            }
        }
        return null;
    }

    private function _addJoinsForTables(ReflectedTable $t1 = null, PathTree $joins = null, array &$records = [],
                                        array $params = [], GenericDB $db = null)   {

        foreach ($joins->getKeys() as $t2Name) {

            $t2 = $this->_reflection->getTable($t2Name);

            $belongsTo = count($t1->getFksTo($t2->getName())) > 0;
            $hasMany = count($t2->getFksTo($t1->getName())) > 0;
            $t3 = $this->_hasAndBelongsToMany($t1, $t2);
            $hasAndBelongsToMany = ($t3 != null);

            $newRecords = array();
            $fkValues = null;
            $pkValues = null;
            $habTmValues = null;

            if ($belongsTo) {
                $fkValues = $this->_getFkEmptyValues($t1, $t2, $records);
                $this->_addFkRecords($t2, $fkValues, $params, $db, $newRecords);
            }
            if ($hasMany) {
                $pkValues = $this->_getPkEmptyValues($t1, $records);
                $this->_addPkRecords($t1, $t2, $pkValues, $params, $db, $newRecords);
            }
            if ($hasAndBelongsToMany) {
                $habTmValues = $this->_getHabtmEmptyValues($t1, $t2, $t3, $db, $records);
                $this->_addFkRecords($t2, $habTmValues->fkValues, $params, $db, $newRecords);
            }

            $this->_addJoinsForTables($t2, $joins->get($t2Name), $newRecords, $params, $db);

            if ($fkValues != null) {
                $this->_fillFkValues($t2, $newRecords, $fkValues);
                $this->_setFkValues($t1, $t2, $records, $fkValues);
            }
            if ($pkValues != null) {
                $this->_fillPkValues($t1, $t2, $newRecords, $pkValues);
                $this->_setPkValues($t1, $t2, $records, $pkValues);
            }
            if ($habTmValues != null) {
                $this->_fillFkValues($t2, $newRecords, $habTmValues->fkValues);
                $this->_setHabtmValues($t1, $t3, $records, $habTmValues);
            }
        }
    }

    private function _getFkEmptyValues(ReflectedTable $t1 = null, ReflectedTable $t2 = null, array $records = [])   {
        $fkValues = array();
        $fks = $t1->getFksTo($t2->getName());
        foreach ($fks as $fk) {
            $fkName = $fk->getName();
            foreach ($records as $record) {
                if (isset($record[$fkName])) {
                    $fkValue = $record[$fkName];
                    $fkValues[$fkValue] = null;
                }
            }
        }
        return $fkValues;
    }

    private function _addFkRecords(ReflectedTable $t2 = null, array $fkValues = [], array $params = [],
                                   GenericDB $db = null, array &$records = []) {
        $columnNames = $this->_columns->getNames($t2, false, $params);
        $fkIds = array_keys($fkValues);

        foreach ($db->selectMultiple($t2, $columnNames, $fkIds) as $record) {
            $records[] = $record;
        }
    }

    private function _fillFkValues(ReflectedTable $t2 = null, array $fkRecords = [], array &$fkValues = []) {
        $pkName = $t2->getPk()->getName();
        foreach ($fkRecords as $fkRecord) {
            $pkValue = $fkRecord[$pkName];
            $fkValues[$pkValue] = $fkRecord;
        }
    }

    private function _setFkValues(ReflectedTable $t1 = null, ReflectedTable $t2 = null, array &$records = [],
                                  array $fkValues = []) {
        $fks = $t1->getFksTo($t2->getName());
        foreach ($fks as $fk) {
            $fkName = $fk->getName();
            foreach ($records as $i => $record) {
                if (isset($record[$fkName])) {
                    $key = $record[$fkName];
                    $records[$i][$fkName] = $fkValues[$key];
                }
            }
        }
    }

    private function _getPkEmptyValues(ReflectedTable $t1 = null, array $records = [])  {
        $pkValues = array();
        $pkName = $t1->getPk()->getName();
        foreach ($records as $record) {
            $key = $record[$pkName];
            $pkValues[$key] = array();
        }
        return $pkValues;
    }

    private function _addPkRecords(ReflectedTable $t1 = null, ReflectedTable $t2 = null, array $pkValues = [],
                                   array $params = [], GenericDB $db = null, array &$records = [])  {
        $fks = $t2->getFksTo($t1->getName());
        $columnNames = $this->_columns->getNames($t2, false, $params);
        $pkValueKeys = implode(',', array_keys($pkValues));
        $conditions = array();
        foreach ($fks as $fk) {
            $conditions[] = new ColumnCondition($fk, 'in', $pkValueKeys);
        }
        $condition = OrCondition::fromArray($conditions);
        foreach ($db->selectAllUnordered($t2, $columnNames, $condition) as $record) {
            $records[] = $record;
        }
    }

    private function _fillPkValues(ReflectedTable $t1 = null, ReflectedTable $t2 = null, array $pkRecords = [],
                                   array &$pkValues = [])  {
        $fks = $t2->getFksTo($t1->getName());
        foreach ($fks as $fk) {
            $fkName = $fk->getName();
            foreach ($pkRecords as $pkRecord) {
                $key = $pkRecord[$fkName];
                if (isset($pkValues[$key])) {
                    $pkValues[$key][] = $pkRecord;
                }
            }
        }
    }

    private function _setPkValues(ReflectedTable $t1 = null, ReflectedTable $t2 = null, array &$records = [], array $pkValues = []) {
        $pkName = $t1->getPk()->getName();
        $t2Name = $t2->getName();

        foreach ($records as $i => $record) {
            $key = $record[$pkName];
            $records[$i][$t2Name] = $pkValues[$key];
        }
    }

    private function _getHabTmEmptyValues(ReflectedTable $t1 = null, ReflectedTable $t2 = null,
                                          ReflectedTable $t3 = null, GenericDB $db = null, array $records = [])   {
        $pkValues = $this->getPkEmptyValues($t1, $records);
        $fkValues = array();

        $fk1 = $t3->getFksTo($t1->getName())[0];
        $fk2 = $t3->getFksTo($t2->getName())[0];

        $fk1Name = $fk1->getName();
        $fk2Name = $fk2->getName();

        $columnNames = array($fk1Name, $fk2Name);

        $pkIds = implode(',', array_keys($pkValues));
        $condition = new ColumnCondition($t3->get($fk1Name), 'in', $pkIds);

        $records = $db->selectAllUnordered($t3, $columnNames, $condition);
        foreach ($records as $record) {
            $val1 = $record[$fk1Name];
            $val2 = $record[$fk2Name];
            $pkValues[$val1][] = $val2;
            $fkValues[$val2] = null;
        }

        return new HabTmValues($pkValues, $fkValues);
    }

    private function _setHabTmValues(ReflectedTable $t1 = null, ReflectedTable $t3 = null, array &$records = [],
                                     HabTmValues $habtmValues = null)   {
        $pkName = $t1->getPk()->getName();
        $t3Name = $t3->getName();
        foreach ($records as $i => $record) {
            $key = $record[$pkName];
            $val = array();
            $fks = $habtmValues->pkValues[$key];
            foreach ($fks as $fk) {
                $val[] = $habtmValues->fkValues[$fk];
            }
            $records[$i][$t3Name] = $val;
        }
    }

    public function __construct(ReflectionService $reflection = null, ColumnInclude $columns = null)   {
        $this->_reflection = $reflection;
        $this->_columns = $columns;
    }

    public function addMandatoryColumns(ReflectedTable $table = null, array &$params = [])  {
        if (!isset($params['join']) || !isset($params['include'])) {
            return;
        }
        $params['mandatory'] = array();
        foreach ($params['join'] as $tableNames) {
            $t1 = $table;
            foreach (explode(',', $tableNames) as $tableName) {
                if (!$this->_reflection->hasTable($tableName)) {
                    continue;
                }
                $t2 = $this->_reflection->getTable($tableName);
                $fks1 = $t1->getFksTo($t2->getName());
                $t3 = $this->_hasAndBelongsToMany($t1, $t2);
                if ($t3 != null || count($fks1) > 0) {
                    $params['mandatory'][] = $t2->getName() . '.' . $t2->getPk()->getName();
                }
                foreach ($fks1 as $fk) {
                    $params['mandatory'][] = $t1->getName() . '.' . $fk->getName();
                }
                $fks2 = $t2->getFksTo($t1->getName());
                if ($t3 != null || count($fks2) > 0) {
                    $params['mandatory'][] = $t1->getName() . '.' . $t1->getPk()->getName();
                }
                foreach ($fks2 as $fk) {
                    $params['mandatory'][] = $t2->getName() . '.' . $fk->getName();
                }
                $t1 = $t2;
            }
        }
    }

    public function addJoins(ReflectedTable $table = null, array &$records = [], array $params = [], GenericDB $db = null)  {
        $joins = $this->_getJoinsAsPathTree($params);
        $this->_addJoinsForTables($table, $joins, $records, $params, $db);
    }

}