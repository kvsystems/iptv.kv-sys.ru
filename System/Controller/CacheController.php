<?php
namespace Evie\Rest\System\Controller;

use Evie\Rest\System\Cache\ICache;
use Evie\Rest\System\Middleware\Router\IRouter;

class CacheController   {

    private $_cache     = null;
    private $_responder = null;

    public function __construct(IRouter $router = null, Responder $responder = null, ICache $cache = null)   {
        $router->register('GET', '/cache/clear', array($this, 'clear'));
        $this->_cache = $cache;
        $this->_responder = $responder;
    }

    public function clear() {
        return $this->_responder->success($this->_cache->clear());
    }

}