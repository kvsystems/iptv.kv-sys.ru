<?php
namespace Evie\Rest\System\Column;

use Evie\Rest\System\Cache\ICache;
use Evie\Rest\System\Column\Reflection\ReflectedDatabase;
use Evie\Rest\System\Column\Reflection\ReflectedTable;
use Evie\Rest\System\Database\GenericDB;

class ReflectionService {

    private $_db        = null;
    private $_cache     = null;
    private $_ttl       = null;
    private $_database  = null;
    private $_tables    = null;

    private function _loadDatabase($useCache = false) {
        $data = $useCache ? $this->_cache->get('ReflectedDatabase') : '';
        if ($data != '') {
            $database = ReflectedDatabase::fromJson(json_decode(gzuncompress($data)));
        } else {
            $database = ReflectedDatabase::fromReflection($this->_db->reflection());
            $data = gzcompress(json_encode($database, JSON_UNESCAPED_UNICODE));
            $this->_cache->set('ReflectedDatabase', $data, $this->_ttl);
        }
        return $database;
    }

    private function _loadTable($tableName = null, $useCache = false)   {
        $data = $useCache ? $this->_cache->get("ReflectedTable($tableName)") : '';
        if ($data != '') {
            $table = ReflectedTable::fromJson(json_decode(gzuncompress($data)));
        } else {
            $table = ReflectedTable::fromReflection($this->_db->reflection(), $tableName);
            $data = gzcompress(json_encode($table, JSON_UNESCAPED_UNICODE));
            $this->_cache->set("ReflectedTable($tableName)", $data, $this->_ttl);
        }
        return $table;
    }

    public function __construct(GenericDB $db = null, ICache $cache = null, $ttl = 0)  {
        $this->_db          = $db;
        $this->_cache       = $cache;
        $this->_ttl         = $ttl;
        $this->_database    = $this->_loadDatabase(true);
        $this->_tables      = [];
    }

    public function refreshTables() {
        $this->_database = $this->_loadDatabase(false);
    }

    public function refreshTable($tableName = null) {
        $this->_tables[$tableName] = $this->_loadTable($tableName, false);
    }

    public function hasTable($tableName = null) {
        return $this->_database->exists($tableName);
    }

    public function getTable($tableName = null) {
        if (!isset($this->_tables[$tableName])) {
            $this->_tables[$tableName] = $this->_loadTable($tableName, true);
        }
        return $this->_tables[$tableName];
    }

    public function getTableNames() {
        return $this->_database->getTableNames();
    }

    public function getDatabaseName()   {
        return $this->_database->getName();
    }

    public function removeTable($tableName = null)  {
        unset($this->_tables[$tableName]);
        return $this->_database->removeTable($tableName);
    }

    public function removeColumn($tableName = null, $columnName = null) {
        return $this->getTable($tableName)->removeColumn($columnName);
    }

}