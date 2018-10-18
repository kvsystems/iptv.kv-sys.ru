<?php
namespace Evie\Rest\System\Record\Condition;

use Evie\Rest\System\Column\Reflection\ReflectedColumn;

class ColumnCondition extends Condition {

    private $_column   = null;
    private $_operator = null;
    private $_value    = null;

    public function __construct(ReflectedColumn $column = null, $operator = null, $value = null)   {
        $this->_column = $column;
        $this->_operator = $operator;
        $this->_value = $value;
    }

    public function getColumn() {
        return $this->_column;
    }

    public function getOperator()   {
        return $this->_operator;
    }

    public function getValue()  {
        return $this->_value;
    }

}