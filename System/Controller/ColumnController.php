<?php
namespace Evie\Rest\System\Controller;

use Evie\Rest\System\Column\DefinitionService;
use Evie\Rest\System\Column\ReflectionService;
use Evie\Rest\System\Middleware\Router\IRouter;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Request;
use Evie\Rest\System\Response;

class ColumnController  {

    private $_responder  = null;
    private $_reflection = null;
    private $_definition = null;

    public function __construct(IRouter $router = null, Responder $responder = null,
                                ReflectionService $reflection = null, DefinitionService $definition = null)    {
        $router->register('GET', '/columns', array($this, 'getDatabase'));
        $router->register('GET', '/columns/*', array($this, 'getTable'));
        $router->register('GET', '/columns/*/*', array($this, 'getColumn'));
        $router->register('PUT', '/columns/*', array($this, 'updateTable'));
        $router->register('PUT', '/columns/*/*', array($this, 'updateColumn'));
        $router->register('POST', '/columns', array($this, 'addTable'));
        $router->register('POST', '/columns/*', array($this, 'addColumn'));
        $router->register('DELETE', '/columns/*', array($this, 'removeTable'));
        $router->register('DELETE', '/columns/*/*', array($this, 'removeColumn'));
        $this->_responder = $responder;
        $this->_reflection = $reflection;
        $this->_definition = $definition;
    }

    public function getDatabase()   {
        $name = $this->reflection->getDatabaseName();
        $tables = [];
        foreach ($this->_reflection->getTableNames() as $table) {
            $tables[] = $this->_reflection->getTable($table);
        }
        $database = ['name' => $name, 'tables' => $tables];
        return $this->_responder->success($database);
    }

    public function getTable(Request $request = null)  {
        $tableName = $request->getPathSegment(2);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $table = $this->_reflection->getTable($tableName);
        return $this->_responder->success($table);
    }

    public function getColumn(Request $request = null) {
        $tableName = $request->getPathSegment(2);
        $columnName = $request->getPathSegment(3);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $table = $this->_reflection->getTable($tableName);
        if (!$table->exists($columnName)) {
            return $this->_responder->error(ErrorCode::COLUMN_NOT_FOUND, $columnName);
        }
        $column = $table->get($columnName);
        return $this->_responder->success($column);
    }

    public function updateTable(Request $request = null)   {
        $tableName = $request->getPathSegment(2);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $success = $this->_definition->updateTable($tableName, $request->getBody());
        if ($success) {
            $this->_reflection->refreshTables();
        }
        return $this->_responder->success($success);
    }

    public function updateColumn(Request $request = null)  {
        $tableName = $request->getPathSegment(2);
        $columnName = $request->getPathSegment(3);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $table = $this->_reflection->getTable($tableName);
        if (!$table->exists($columnName)) {
            return $this->_responder->error(ErrorCode::COLUMN_NOT_FOUND, $columnName);
        }
        $success = $this->_definition->updateColumn($tableName, $columnName, $request->getBody());
        if ($success) {
            $this->_reflection->refreshTable($tableName);
        }
        return $this->_responder->success($success);
    }

    public function addTable(Request $request = null)  {
        $tableName = $request->getBody()->name;
        if ($this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_ALREADY_EXISTS, $tableName);
        }
        $success = $this->_definition->addTable($request->getBody());
        if ($success) {
            $this->_reflection->refreshTables();
        }
        return $this->_responder->success($success);
    }

    public function addColumn(Request $request = null) {
        $tableName = $request->getPathSegment(2);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $columnName = $request->getBody()->name;
        $table = $this->_reflection->getTable($tableName);
        if ($table->exists($columnName)) {
            return $this->_responder->error(ErrorCode::COLUMN_ALREADY_EXISTS, $columnName);
        }
        $success = $this->_definition->addColumn($tableName, $request->getBody());
        if ($success) {
            $this->_reflection->refreshTable($tableName);
        }
        return $this->_responder->success($success);
    }

    public function removeTable(Request $request = null)   {
        $tableName = $request->getPathSegment(2);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $success = $this->_definition->removeTable($tableName);
        if ($success) {
            $this->_reflection->refreshTables();
        }
        return $this->_responder->success($success);
    }

    public function removeColumn(Request $request = null)  {
        $tableName = $request->getPathSegment(2);
        $columnName = $request->getPathSegment(3);
        if (!$this->_reflection->hasTable($tableName)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
        }
        $table = $this->_reflection->getTable($tableName);
        if (!$table->exists($columnName)) {
            return $this->_responder->error(ErrorCode::COLUMN_NOT_FOUND, $columnName);
        }
        $success = $this->_definition->removeColumn($tableName, $columnName);
        if ($success) {
            $this->_reflection->refreshTable($tableName);
        }
        return $this->_responder->success($success);
    }

}