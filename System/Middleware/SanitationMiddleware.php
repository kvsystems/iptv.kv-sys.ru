<?php
namespace Evie\Rest\System\Middleware;

use Evie\Rest\System\Column\ReflectionService;
use Evie\Rest\System\Column\Reflection\ReflectedTable;
use Evie\Rest\System\Controller\Responder;
use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Middleware\Router\IRouter;
use Evie\Rest\System\Request;

class SanitationMiddleware extends Middleware   {

    private $_reflection = null;

    private function _callHandler($handler = null, $record = null, $method = null, ReflectedTable $table = null)    {
        $context = (array) $record;
        $tableName = $table->getName();
        foreach ($context as $columnName => &$value) {
            if ($table->exists($columnName)) {
                $column = $table->get($columnName);
                $value = call_user_func($handler, $method, $tableName, $column->serialize(), $value);
            }
        }
        return (object) $context;
    }

    public function __construct(IRouter $router = null, Responder $responder = null, array $properties = [],
                                ReflectionService $reflection = null) {
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
                    foreach ($record as &$r) {
                        $r = $this->_callHandler($handler, $r, $method, $table);
                    }
                } else {
                    $record = $this->_callHandler($handler, $record, $method, $table);
                }
                $path = $request->getPath();
                $query = urldecode(http_build_query($request->getParams()));
                $headers = $request->getHeaders();
                $body = json_encode($record);
                $request = new Request($method, $path, $query, $headers, $body);
            }
        }
        return $this->next->handle($request);
    }
}