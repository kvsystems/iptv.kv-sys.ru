<?php
namespace Evie\Rest\System\Record\Condition;

class OrCondition extends Condition {

    private $_conditions = [];

    public function __construct(Condition $condition1 = null, Condition $condition2 = null)   {
        $this->_conditions = [$condition1, $condition2];
    }

    public function thisOr(Condition $condition = null)   {
        if ($condition instanceof NoCondition) {
            return $this;
        }
        $this->_conditions[] = $condition;
        return $this;
    }

    public function getConditions() {
        return $this->_conditions;
    }

    public static function fromArray(array $conditions = []) {
        $condition = new NoCondition();
        foreach ($conditions as $c) {
            $condition = $condition->thisOr($c);
        }
        return $condition;
    }

}