<?php
namespace Evie\Rest\System\Middleware;

use Evie\Rest\System\Column\ReflectionService;
use Evie\Rest\System\Column\Reflection\ReflectedTable;
use Evie\Rest\System\Controller\Responder;
use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Middleware\Router\IRouter;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Request;

class ValidationMiddleware extends Middleware   {

    private $_reflection = null;

    private function _callHandler($handler = null, $record = null, $method = null, ReflectedTable $table = null)    {
        $context = (array) $record;
        $details = array();
        $tableName = $table->getName();
        foreach ($context as $columnName => $value) {
            if ($table->exists($columnName)) {
                $column = $table->get($columnName);
                $valid = call_user_func($handler, $method, $tableName, $column->serialize(), $value, $context);
                if ($valid !== true && $valid !== '') {
                    $details[$columnName] = $valid;
                }
            }
        }
        if (count($details) > 0) {
            return $this->responder->error(ErrorCode::INPUT_VALIDATION_FAILED, $tableName, $details);
        }
        return null;
    }

    public function __construct(IRouter $router = null, Responder $responder = null, array $properties = [],
                                ReflectionService $reflection = null)   {
        parent::__construct($router, $responder, $properties);
        $this->_reflection = $reflection;
    }

    public function handle(Request $request = null)    {
        $path = $request->getPathSegment(1);
        $tableName = $request->getPathSegment(2);
        $record = $request->getBody();
        if ($path == 'records' && $this->_reflection->hasTable($tableName) && $record !== null) {
            $table = $this->_reflection->getTable($tableName);
            $method = $request->getMethod();
            $handler = $this->getProperty('handler', '');
            if ($handler !== '') {
                if (is_array($record)) {
                    foreach ($record as $r) {
                        $response = $this->_callHandler($handler, $r, $method, $table);
                        if ($response !== null) {
                            return $response;
                        }
                    }
                } else {
                    $response = $this->_callHandler($handler, $record, $method, $table);
                    if ($response !== null) {
                        return $response;
                    }
                }
            }
        }
        return $this->next->handle($request);
    }

}