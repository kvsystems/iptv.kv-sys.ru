<?php
namespace Evie\Rest\System\Middleware\Base;

use Evie\Rest\System\Controller\Responder;
use Evie\Rest\System\Middleware\Router\IRouter;

abstract class Middleware implements IHandler   {

    private $_properties = null;

    protected $next      = null;
    protected $responder = null;


    public function __construct(IRouter $router = null, Responder $responder = null, array $properties =[])    {
        $router->load($this);
        $this->responder = $responder;
        $this->_properties = $properties;
    }

    public function setNext(IHandler $handler = null)   {
        $this->next = $handler;
    }

    protected function getProperty($key = null, $default = null)   {
        return isset($this->properties[$key]) ? $this->properties[$key] : $default;
    }

}
