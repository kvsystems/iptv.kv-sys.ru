<?php
namespace Evie\Rest\System\Record\Document;

class ListDocument implements \JsonSerializable {

    private $_records = null;
    private $_results = null;

    public function __construct(array $records = [], $results = 0)   {
        $this->_records = $records;
        $this->_results = $results;
    }

    public function getRecords()    {
        return $this->_records;
    }

    public function getResults()    {
        return $this->_results;
    }

    public function serialize() {
        return [
            'records' => $this->_records,
            'results' => $this->_results,
        ];
    }

    public function jsonSerialize() {
        return array_filter($this->serialize(), function ($v) {
            return $v !== 0;
        });
    }
}