<?php
namespace Evie\Rest\System\Record;

class HabTmValues   {

    public $pkValues;
    public $fkValues;

    public function __construct(array $pkValues = [], array $fkValues = [])   {
        $this->pkValues = $pkValues;
        $this->fkValues = $fkValues;
    }

}