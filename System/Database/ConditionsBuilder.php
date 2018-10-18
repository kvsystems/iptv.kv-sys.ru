<?php
namespace Evie\Rest\System\Database;

use Evie\Rest\System\Record\Condition\AndCondition;
use Evie\Rest\System\Record\Condition\ColumnCondition;
use Evie\Rest\System\Record\Condition\Condition;
use Evie\Rest\System\Record\Condition\NoCondition;
use Evie\Rest\System\Record\Condition\NotCondition;
use Evie\Rest\System\Record\Condition\OrCondition;
use Evie\Rest\System\Record\Condition\SpatialCondition;
use Evie\Rest\System\Column\Reflection\ReflectedColumn;

class ConditionsBuilder {

    const DEFAULT_DRIVER    = 'mysql';
    const PG_SQL_DRIVER     = 'pg_sql';
    const SQL_SRV_DRIVER    = 'sql_srv';

    private $_driver = null;

    private function _getConditionSql(Condition $condition, array &$arguments)   {
        if ($condition instanceof AndCondition) {
            return $this->getAndConditionSql($condition, $arguments);
        }
        if ($condition instanceof OrCondition) {
            return $this->getOrConditionSql($condition, $arguments);
        }
        if ($condition instanceof NotCondition) {
            return $this->getNotConditionSql($condition, $arguments);
        }
        if ($condition instanceof ColumnCondition) {
            return $this->getColumnConditionSql($condition, $arguments);
        }
        if ($condition instanceof SpatialCondition) {
            return $this->getSpatialConditionSql($condition, $arguments);
        }
        throw new \Exception('Unknown Condition: ' . get_class($condition));
    }

    private function _getAndConditionSql(AndCondition $and, array &$arguments)  {
        $parts = [];
        foreach ($and->getConditions() as $condition) {
            $parts[] = $this->_getConditionSql($condition, $arguments);
        }
        return '(' . implode(' AND ', $parts) . ')';
    }

    private function _getOrConditionSql(OrCondition $or, array &$arguments)  {
        $parts = [];
        foreach ($or->getConditions() as $condition) {
            $parts[] = $this->_getConditionSql($condition, $arguments);
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    private function _getNotConditionSql(NotCondition $not, array &$arguments)   {
        $condition = $not->getCondition();
        return '(NOT ' . $this->_getConditionSql($condition, $arguments) . ')';
    }

    private function _quoteColumnName(ReflectedColumn $column)  {
        return '"' . $column->getName() . '"';
    }

    private function _escapeLikeValue($value = null)    {
        return addcslashes($value, '%_');
    }

    private function _getColumnConditionSql(ColumnCondition $condition, array &$arguments)  {
        $column = $this->_quoteColumnName($condition->getColumn());
        $operator = $condition->getOperator();
        $value = $condition->getValue();
        switch ($operator) {
            case 'cs':
                $sql = "$column LIKE ?";
                $arguments[] = '%' . $this->_escapeLikeValue($value) . '%';
                break;
            case 'sw':
                $sql = "$column LIKE ?";
                $arguments[] = $this->_escapeLikeValue($value) . '%';
                break;
            case 'ew':
                $sql = "$column LIKE ?";
                $arguments[] = '%' . $this->_escapeLikeValue($value);
                break;
            case 'eq':
                $sql = "$column = ?";
                $arguments[] = $value;
                break;
            case 'lt':
                $sql = "$column < ?";
                $arguments[] = $value;
                break;
            case 'le':
                $sql = "$column <= ?";
                $arguments[] = $value;
                break;
            case 'ge':
                $sql = "$column >= ?";
                $arguments[] = $value;
                break;
            case 'gt':
                $sql = "$column > ?";
                $arguments[] = $value;
                break;
            case 'bt':
                $parts = explode(',', $value, 2);
                $count = count($parts);
                if ($count == 2) {
                    $sql = "($column >= ? AND $column <= ?)";
                    $arguments[] = $parts[0];
                    $arguments[] = $parts[1];
                } else {
                    $sql = "FALSE";
                }
                break;
            case 'in':
                $parts = explode(',', $value);
                $count = count($parts);
                if ($count > 0) {
                    $qmarks = implode(',', str_split(str_repeat('?', $count)));
                    $sql = "$column IN ($qmarks)";
                    for ($i = 0; $i < $count; $i++) {
                        $arguments[] = $parts[$i];
                    }
                } else {
                    $sql = "FALSE";
                }
                break;
            case 'is':
                $sql = "$column IS NULL";
                break;
        }
        return $sql;
    }

    private function _getSpatialFunctionName($operator = null)  {
        switch ($operator) {
            case 'co':return 'ST_Contains';
            case 'cr':return 'ST_Crosses';
            case 'di':return 'ST_Disjoint';
            case 'eq':return 'ST_Equals';
            case 'in':return 'ST_Intersects';
            case 'ov':return 'ST_Overlaps';
            case 'to':return 'ST_Touches';
            case 'wi':return 'ST_Within';
            case 'ic':return 'ST_IsClosed';
            case 'is':return 'ST_IsSimple';
            case 'iv':return 'ST_IsValid';
        }
    }

    private function _hasSpatialArgument($operator = null)  {
        return in_array($operator, ['ic', 'is', 'iv']) ? false : true;
    }

    private function _getSpatialFunctionCall($functionName = null, $column = null, $hasArgument = false)    {
        switch ($this->_driver) {
            case self::PG_SQL_DRIVER:
                $argument = $hasArgument ? 'ST_GeomFromText(?)' : '';
                return "$functionName($column, $argument)=TRUE";
            case self::SQL_SRV_DRIVER:
                $functionName = str_replace('ST_', 'ST', $functionName);
                $argument = $hasArgument ? 'geometry::STGeomFromText(?,0)' : '';
                return "$column.$functionName($argument)=1";
            default:
        }
    }

    private function _getSpatialConditionSql(ColumnCondition $condition, array &$arguments) {
        $column = $this->_quoteColumnName($condition->getColumn());
        $operator = $condition->getOperator();
        $value = $condition->getValue();
        $functionName = $this->_getSpatialFunctionName($operator);
        $hasArgument = $this->_hasSpatialArgument($operator);
        $sql = $this->_getSpatialFunctionCall($functionName, $column, $hasArgument);
        if ($hasArgument) {
            $arguments[] = $value;
        }
        return $sql;
    }

    public function __construct($driver = null)   {
        $this->_driver = $driver;
    }

    public function getWhereClause(Condition $condition, array &$arguments) {
        if ($condition instanceof NoCondition) {
            return '';
        }
        return ' WHERE ' . $this->_getConditionSql($condition, $arguments);
    }

}