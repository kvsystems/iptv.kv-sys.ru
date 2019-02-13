<?php
namespace Evie\Rest\System\Record;

use Evie\Rest\System\Record\Condition\AndCondition;
use Evie\Rest\System\Record\Condition\Condition;
use Evie\Rest\System\Record\Condition\OrCondition;
use Evie\Rest\System\Column\Reflection\ReflectedTable;

class FilterInfo    {

    private function _addConditionFromFilterPath(PathTree $conditions = null, array $path = [],
                                                 ReflectedTable $table = null, array $params = [])  {
        $key = 'filter' . implode('', $path);
        if (isset($params[$key])) {
            foreach ($params[$key] as $filter) {
                $condition = Condition::fromString($table, $filter);
                if ($condition != null) {
                    $conditions->put($path, $condition);
                }
            }
        }
    }

    private function _getConditionsAsPathTree(ReflectedTable $table = null, array $params = []) {
        $conditions = new PathTree();
        $this->_addConditionFromFilterPath($conditions, [], $table, $params);
        for ($n = ord('0'); $n <= ord('9'); $n++) {
            $this->_addConditionFromFilterPath($conditions, [chr($n)], $table, $params);
            for ($l = ord('a'); $l <= ord('f'); $l++) {
                $this->_addConditionFromFilterPath($conditions, [chr($n), chr($l)], $table, $params);
            }
        }
        return $conditions;
    }

    private function _combinePathTreeOfConditions(PathTree $tree = null)   {
        $andConditions = $tree->getValues();
        $and = AndCondition::fromArray($andConditions);
        $orConditions = [];
        foreach ($tree->getKeys() as $p) {
            $orConditions[] = $this->_combinePathTreeOfConditions($tree->get($p));
        }
        $or = OrCondition::fromArray($orConditions);
        return $and->thisAnd($or);
    }

    public function getCombinedConditions(ReflectedTable $table = null, array $params = []) {
        return $this->_combinePathTreeOfConditions($this->_getConditionsAsPathTree($table, $params));
    }

}