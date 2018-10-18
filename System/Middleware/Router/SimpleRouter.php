<?php
namespace Evie\Rest\System\Middleware\Router;

use Evie\Rest\System\Cache\ICache;
use Evie\Rest\System\Controller\Responder;
use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Record\PathTree;
use Evie\Rest\System\Request;

class SimpleRouter implements IRouter    {

    const CACHE_PATH_KEY = 'PathTree';

    private $_responder      = null;
    private $_cache          = null;
    private $_ttl            = null;
    private $_routes         = null;
    private $_registration   = true;
    private $_routeHandlers  = [];
    private $_middleware     = [];

    private function _loadPathTree()    {
        $data = $this->_cache->get(self::CACHE_PATH_KEY);
        if ($data != '') {
            $tree = PathTree::fromJson(json_decode(gzuncompress($data)));
            $this->_registration = false;
        } else {
            $tree = new PathTree();
        }
        return $tree;
    }

    private function _getRouteNumbers(Request $request = null) {
        $method = strtoupper($request->getMethod());
        $path = explode('/', trim($request->getPath(), '/'));
        array_unshift($path, $method);
        return $this->_routes->match($path);
    }

    public function __construct(Responder $responder, ICache $cache, $ttl = 0)   {
        $this->_responder = $responder;
        $this->_cache = $cache;
        $this->_ttl = $ttl;
        $this->_routes = $this->_loadPathTree();
    }

    public function register($method = null, $path = null, array $handler = []) {
        $routeNumber = count($this->_routeHandlers);
        $this->_routeHandlers[$routeNumber] = $handler;
        if ($this->_registration) {
            $parts = explode('/', trim($path, '/'));
            array_unshift($parts, $method);
            $this->_routes->put($parts, $routeNumber);
        }
    }

    public function load(Middleware $middleware = null) {
        if (count($this->_middleware) > 0) {
            $next = $this->_middleware[0];
        } else {
            $next = $this;
        }
        $middleware->setNext($next);
        array_unshift($this->_middleware, $middleware);
    }

    public function route(Request $request = null)  {
        if ($this->_registration) {
            $data = gzcompress(json_encode($this->_routes, JSON_UNESCAPED_UNICODE));
            $this->_cache->set('PathTree', $data, $this->_ttl);
        }
        $obj = $this;
        if (count($this->_middleware) > 0) {
            $obj = $this->_middleware[0];
        }
        return $obj->handle($request);
    }

    public function handle(Request $request)    {
        $routeNumbers = $this->_getRouteNumbers($request);
        if (count($routeNumbers) == 0) {
            return $this->_responder->error(ErrorCode::ROUTE_NOT_FOUND, $request->getPath());
        }
        return call_user_func($this->_routeHandlers[$routeNumbers[0]], $request);
    }

}
