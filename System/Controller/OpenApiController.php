<?php
namespace Evie\Rest\System\Controller;

use Evie\Rest\System\OpenApi\OpenApiService;
use Evie\Rest\System\Middleware\Router\IRouter;

class OpenApiController {

    private $_openApi   = null;
    private $_responder = null;

    public function __construct(IRouter $router = null, Responder $responder = null, OpenApiService $openApi = null)   {
        $router->register('GET', '/openapi', array($this, 'openapi'));
        $this->_openApi     = $openApi;
        $this->_responder   = $responder;
    }

    public function openApi()   {
        return $this->_responder->success(false);
    }


}