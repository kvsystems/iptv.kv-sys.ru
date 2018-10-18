<?php
namespace Evie\Rest\System\Middleware;

use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Middleware\Communication\VariableStore;
use Evie\Rest\System\Record\FilterInfo;
use Evie\Rest\System\Request;

class AuthorizationMiddleware extends Middleware    {

    private $_reflection = null;

    private function _handleColumns($method = null, $path = null, $databaseName = null, $tableName = null)  {
        $columnHandler = $this->getProperty('columnHandler', '');
        if ($columnHandler) {
            $table = $this->_reflection->getTable($tableName);
            foreach ($table->columnNames() as $columnName) {
                $allowed = call_user_func($columnHandler, $method, $path, $databaseName, $tableName, $columnName);
                if (!$allowed) {
                    $this->_reflection->removeColumn($tableName, $columnName);
                }
            }
        }
    }

    private function _handleTable($method = null, $path = null, $databaseName = null, $tableName = null) {
        if (!$this->_reflection->hasTable($tableName)) {
            return;
        }
        $tableHandler = $this->getProperty('tableHandler', '');
        if ($tableHandler) {
            $allowed = call_user_func($tableHandler, $method, $path, $databaseName, $tableName);
            if (!$allowed) {
                $this->_reflection->removeTable($tableName);
            } else {
                $this->_handleColumns($method, $path, $databaseName, $tableName);
            }
        }
    }

    private function _handleJoinTables($method = null, $path = null, $databaseName = null, array $joinParameters = [])  {
        $uniqueTableNames = array();
        foreach ($joinParameters as $joinParameter) {
            $tableNames = explode(',', trim($joinParameter));
            foreach ($tableNames as $tableName) {
                $uniqueTableNames[$tableName] = true;
            }
        }
        foreach (array_keys($uniqueTableNames) as $tableName) {
            $this->_handleTable($method, $path, $databaseName, trim($tableName));
        }
    }

    private function _handleAllTables($method = null, $path = null, $databaseName = null)   {
        $tableNames = $this->_reflection->getTableNames();
        foreach ($tableNames as $tableName) {
            $this->_handleTable($method, $path, $databaseName, $tableName);
        }
    }

    private function _handleRecords($method = null, $path = null, $databaseName = null, $tableName = null)  {
        if (!$this->_reflection->hasTable($tableName)) {
            return;
        }
        $recordHandler = $this->getProperty('recordHandler', '');
        if ($recordHandler) {
            $query = call_user_func($recordHandler, $method, $path, $databaseName, $tableName);
            $filters = new FilterInfo();
            $table = $this->_reflection->getTable($tableName);
            $query = str_replace('][]=', ']=', str_replace('=', '[]=', $query));
            parse_str($query, $params);
            $condition = $filters->getCombinedConditions($table, $params);
            VariableStore::set('authorization.condition', $condition);
        }
    }

    public function handle(Request $request = null) {
        $method = $request->getMethod();
        $path = $request->getPathSegment(1);
        $databaseName = $this->_reflection->getDatabaseName();
        if ($path == 'records') {
            $tableName = $request->getPathSegment(2);
            $this->_handleTable($method, $path, $databaseName, $tableName);
            $params = $request->getParams();
            if (isset($params['join'])) {
                $this->_handleJoinTables($method, $path, $databaseName, $params['join']);
            }
            $this->_handleRecords($method, $path, $databaseName, $tableName);
        } elseif ($path == 'columns') {
            $tableName = $request->getPathSegment(2);
            if ($tableName) {
                $this->_handleTable($method, $path, $databaseName, $tableName);
            } else {
                $this->_handleAllTables($method, $path, $databaseName);
            }
        } elseif ($path == 'openapi') {
            $this->_handleAllTables($method, $path, $databaseName);
        }
        return $this->next->handle($request);
    }
}