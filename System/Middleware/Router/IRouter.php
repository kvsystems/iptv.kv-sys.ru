<?php
namespace Evie\Rest\System\Middleware\Router;

use Evie\Rest\System\Middleware\Base\IHandler;
use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Request;

interface IRouter extends IHandler   {

    public function register($method = null, $path = null, array $handler = []);

    public function load(Middleware $middleware = null);

    public function route(Request $request = null);

}