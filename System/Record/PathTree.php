<?php
namespace Evie\Rest\System\Record;

class PathTree implements \JsonSerializable {

    const WILDCARD = '*';

    private $_tree = null;

    public function __construct(&$tree = null)   {
        if (!$tree) {
            $tree = $this->newTree();
        }
        $this->_tree = &$tree;
    }

    public function newTree()   {
        return (object) ['values' => [], 'branches' => (object) []];
    }

    public function getKeys()   {
        $branches = (array) $this->_tree->branches;
        return array_keys($branches);
    }

    public function getValues() {
        return $this->_tree->values;
    }

    public function get($key = null)    {
        if (!isset($this->_tree->branches->$key)) {
            return null;
        }
        return new PathTree($this->_tree->branches->$key);
    }

    public function put(array $path = [], $value = null)
    {
        $tree = &$this->_tree;
        foreach ($path as $key) {
            if (!isset($tree->branches->$key)) {
                $tree->branches->$key = $this->newTree();
            }
            $tree = &$tree->branches->$key;
        }
        $tree->values[] = $value;
    }

    public function match(array $path = []) {
        $star = self::WILDCARD;
        $tree = &$this->_tree;
        foreach (array_filter($path) as $key) {
            if (isset($tree->branches->$key)) {
                $tree = &$tree->branches->$key;
            } else if (isset($tree->branches->$star)) {
                $tree = &$tree->branches->$star;
            } else {
                return [];
            }
        }

        return $tree->values;
    }

    public static function fromJson($tree = null)   {
        return new PathTree($tree);
    }

    public function jsonSerialize()
    {
        return $this->_tree;
    }

}