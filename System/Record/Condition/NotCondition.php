<?php
namespace Evie\Rest\System\Record\Condition;

class NotCondition extends  Condition   {

    private $_condition = null;

    public function __construct(Condition $condition = null)   {
        $this->_condition = $condition;
    }

    public function getCondition()  {
        return $this->_condition;
    }

}