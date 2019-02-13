<?php
namespace Evie\Rest\System\Record;

use Evie\Rest\System\Column\ReflectionService;
use Evie\Rest\System\Database\GenericDB;
use Evie\Rest\System\Record\Document\ListDocument;

class RecordService {

    private $_db            = null;
    private $_reflection    = null;
    private $_columns       = null;
    private $_joiner        = null;
    private $_filters       = null;
    private $_ordering      = null;
    private $_pagination    = null;

    private function _sanitizeRecord($tableName = null, $record = null, $id = null)    {
        $keySet = array_keys((array) $record);
        foreach ($keySet as $key) {
            if (!$this->_reflection->getTable($tableName)->exists($key)) {
                unset($record->$key);
            }
        }
        if ($id != '') {
            $pk = $this->_reflection->getTable($tableName)->getPk();
            foreach ($this->_reflection->getTable($tableName)->columnNames() as $key) {
                $field = $this->_reflection->getTable($tableName)->get($key);
                if ($field->getName() == $pk->getName()) {
                    unset($record->$key);
                }
            }
        }
    }

    public function __construct(GenericDB $db = null, ReflectionService $reflection = null)
    {
        $this->_db = $db;
        $this->_reflection = $reflection;
        $this->_columns = new ColumnInclude();
        $this->_joiner = new RelationJoiner($reflection, $this->_columns);
        $this->_filters = new FilterInfo();
        $this->_ordering = new OrderingInfo();
        $this->_pagination = new PaginationInfo();
    }

    public function exists($table = null)   {
        return $this->_reflection->hasTable($table);
    }

    public function create($tableName = null, $record = null, array $params = [])
    {
        $this->_sanitizeRecord($tableName, $record, '');
        $table = $this->_reflection->getTable($tableName);
        $columnValues = $this->_columns->getValues($table, true, $record, $params);
        return $this->_db->createSingle($table, $columnValues);
    }

    public function read($tableName = null, $id = null, array $params = []) {
        $table = $this->_reflection->getTable($tableName);
        $this->_joiner->addMandatoryColumns($table, $params);
        $columnNames = $this->_columns->getNames($table, true, $params);
        $record = $this->_db->getSingle($table, $columnNames, $id);
        if ($record == null) {
            return null;
        }
        $records = array($record);
        $this->_joiner->addJoins($table, $records, $params, $this->_db);
        return $records[0];
    }

    public function update($tableName = null, $id = null, $record = null, array $params = [])   {
        $this->_sanitizeRecord($tableName, $record, $id);
        $table = $this->_reflection->getTable($tableName);
        $columnValues = $this->_columns->getValues($table, true, $record, $params);
        return $this->_db->updateSingle($table, $columnValues, $id);
    }

    public function delete($tableName = null, $id = null, array $params = [])   {
        $table = $this->_reflection->getTable($tableName);
        return $this->_db->deleteSingle($table, $id);
    }

    public function increment($tableName = null, $id = null, $record = null, array $params = [])    {
        $this->_sanitizeRecord($tableName, $record, $id);
        $table = $this->_reflection->getTable($tableName);
        $columnValues = $this->_columns->getValues($table, true, $record, $params);
        return $this->_db->incrementSingle($table, $columnValues, $id);
    }

    public function toList($tableName = null, array $params = [])   {
        $table = $this->_reflection->getTable($tableName);
        $this->_joiner->addMandatoryColumns($table, $params);
        $columnNames = $this->_columns->getNames($table, true, $params);
        $condition = $this->_filters->getCombinedConditions($table, $params);
        $columnOrdering = $this->_ordering->getColumnOrdering($table, $params);
        if (!$this->_pagination->hasPage($params)) {
            $offset = 0;
            $limit = $this->_pagination->getResultSize($params);
            $count = 0;
        } else {
            $offset = $this->_pagination->getPageOffset($params);
            $limit = $this->_pagination->getPageSize($params);
            $count = $this->_db->selectCount($table, $condition);
        }
        $records = $this->_db->selectAll($table, $columnNames, $condition, $columnOrdering, $offset, $limit);
        $this->_joiner->addJoins($table, $records, $params, $this->_db);
        return new ListDocument($records, $count);
    }

}