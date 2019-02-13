<?php
namespace Evie\Rest\System\Controller;

use Evie\Rest\System\Middleware\Router\IRouter;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Record\RecordService;
use Evie\Rest\System\Request;

class RecordController  {

    private $_service   = null;
    private $_responder = null;

    public function __construct(IRouter $router = null, Responder $responder = null, RecordService $service = null)  {
        $router->register('GET', '/records/*', array($this, '_list'));
        $router->register('POST', '/records/*', array($this, 'create'));
        $router->register('GET', '/records/*/*', array($this, 'read'));
        $router->register('PUT', '/records/*/*', array($this, 'update'));
        $router->register('DELETE', '/records/*/*', array($this, 'delete'));
        $router->register('PATCH', '/records/*/*', array($this, 'increment'));
        $this->_service = $service;
        $this->_responder = $responder;
    }

    public function _list(Request $request = null) {
        $table = $request->getPathSegment(2);
        $params = $request->getParams();
        $pos = strripos($table, '?');
        if($pos)    {
            $table = explode('?', $table)[0];
        }
        if (!$this->_service->exists($table)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
        }
        return $this->_responder->success($this->_service->toList($table, $params));
    }

    public function read(Request $request = null)  {
        $table = $request->getPathSegment(2);
        $id = $request->getPathSegment(3);
        $params = $request->getParams();
        if (!$this->_service->exists($table)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
        }
        if (strpos($id, ',') !== false) {
            $ids = explode(',', $id);
            $result = [];
            for ($i = 0; $i < count($ids); $i++) {
                array_push($result, $this->_service->read($table, $ids[$i], $params));
            }
            return $this->_responder->success($result);
        } else {
            $response = $this->_service->read($table, $id, $params);
            if ($response === null) {
                return $this->_responder->error(ErrorCode::RECORD_NOT_FOUND, $id);
            }
            return $this->_responder->success($response);
        }
    }

    public function create(Request $request = null)    {
        $table = $request->getPathSegment(2);
        $record = $request->getBody();
        if ($record === null) {
            return $this->_responder->error(ErrorCode::HTTP_MESSAGE_NOT_READABLE, '');
        }
        $params = $request->getParams();
        if (!$this->_service->exists($table)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
        }
        if (is_array($record)) {
            $result = array();
            foreach ($record as $r) {
                $result[] = $this->_service->create($table, $r, $params);
            }
            return $this->_responder->success($result);
        } else {
            return $this->_responder->success($this->_service->create($table, $record, $params));
        }
    }

    public function update(Request $request = null)    {
        $table = $request->getPathSegment(2);
        $id = $request->getPathSegment(3);
        $record = $request->getBody();
        if ($record === null) {
            return $this->_responder->error(ErrorCode::HTTP_MESSAGE_NOT_READABLE, '');
        }
        $params = $request->getParams();
        if (!$this->_service->exists($table)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
        }
        $ids = explode(',', $id);
        if (is_array($record)) {
            if (count($ids) != count($record)) {
                return $this->_responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
            }
            $result = array();
            for ($i = 0; $i < count($ids); $i++) {
                $result[] = $this->_service->update($table, $ids[$i], $record[$i], $params);
            }
            return $this->_responder->success($result);
        } else {
            if (count($ids) != 1) {
                return $this->_responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
            }
            return $this->_responder->success($this->_service->update($table, $id, $record, $params));
        }
    }

    public function delete(Request $request = null)    {
        $table = $request->getPathSegment(2);
        $id = $request->getPathSegment(3);
        $params = $request->getParams();
        if (!$this->_service->exists($table)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
        }
        $ids = explode(',', $id);

        if (count($ids) > 1) {
            $result = array();
            for ($i = 0; $i < count($ids); $i++) {
                $result[] = $this->_service->delete($table, $ids[$i], $params);
            }
            return $this->_responder->success($result);
        } else {
            return $this->_responder->success($this->_service->delete($table, $id, $params));
        }
    }

    public function increment(Request $request = null) {
        $table = $request->getPathSegment(2);
        $id = $request->getPathSegment(3);
        $record = $request->getBody();
        if ($record === null) {
            return $this->_responder->error(ErrorCode::HTTP_MESSAGE_NOT_READABLE, '');
        }
        $params = $request->getParams();
        if (!$this->_service->exists($table)) {
            return $this->_responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
        }
        $ids = explode(',', $id);
        if (is_array($record)) {
            if (count($ids) != count($record)) {
                return $this->_responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
            }
            $result = array();
            for ($i = 0; $i < count($ids); $i++) {
                $result[] = $this->_service->increment($table, $ids[$i], $record[$i], $params);
            }
            return $this->_responder->success($result);
        } else {
            if (count($ids) != 1) {
                return $this->_responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
            }
            return $this->_responder->success($this->_service->increment($table, $id, $record, $params));
        }
    }

}