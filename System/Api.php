<?php
namespace Evie\Rest\System;

use Evie\Rest\System\Cache\CacheFactory;
use Evie\Rest\System\Column\DefinitionService;
use Evie\Rest\System\Column\ReflectionService;
use Evie\Rest\System\Controller\CacheController;
use Evie\Rest\System\Controller\ColumnController;
use Evie\Rest\System\Controller\OpenApiController;
use Evie\Rest\System\Controller\RecordController;
use Evie\Rest\System\Controller\Responder;
use Evie\Rest\System\Database\GenericDB;
use Evie\Rest\System\Middleware\AuthorizationMiddleware;
use Evie\Rest\System\Middleware\CorsMiddleware;
use Evie\Rest\System\Middleware\FirewallMiddleware;
use Evie\Rest\System\Middleware\Router\SimpleRouter;
use Evie\Rest\System\Middleware\SanitationMiddleware;
use Evie\Rest\System\Middleware\ValidationMiddleware;
use Evie\Rest\System\OpenApi\OpenApiService;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Record\RecordService;

class Api {

    const CORS_MIDDLEWARE           = 'cors';
    const FIREWALL_MIDDLEWARE       = 'firewall';
    const BASIC_AUTH_MIDDLEWARE     = 'basicAuth';
    const VALIDATION_MIDDLEWARE     = 'validation';
    const SANITATION_MIDDLEWARE     = 'sanitation';
    const AUTHORIZATION_MIDDLEWARE  = 'authorization';

    const RECORDS_CONTROLLER    = 'records';
    const COLUMNS_CONTROLLER    = 'columns';
    const CACHE_CONTROLLER      = 'cache';
    const OPEN_API_CONTROLLER   = 'openapi';

    private $_router    = null;
    private $_responder = null;
    private $_debug     = null;

    public function __construct(Config $config)   {
        $db = new GenericDB(
            $config->getDriver(),
            $config->getHost(),
            $config->getPort(),
            $config->getDatabase(),
            $config->getUsername(),
            $config->getPassword()
        );

        $cache = CacheFactory::create($config);
        $reflection = new ReflectionService($db, $cache, $config->getCacheTime());
        $responder = new Responder();
        $router = new SimpleRouter($responder, $cache, $config->getCacheTime());

        foreach ($config->getMiddleware() as $middleware => $properties) {
            switch ($middleware) {
                case self::CORS_MIDDLEWARE:
                    new CorsMiddleware($router, $responder, $properties);
                    break;
                case self::FIREWALL_MIDDLEWARE:
                    new FirewallMiddleware($router, $responder, $properties);
                    break;
                case self::BASIC_AUTH_MIDDLEWARE:
                    new BasicAuthMiddleware($router, $responder, $properties);
                    break;
                case self::VALIDATION_MIDDLEWARE:
                    new ValidationMiddleware($router, $responder, $properties, $reflection);
                    break;
                case self::SANITATION_MIDDLEWARE:
                    new SanitationMiddleware($router, $responder, $properties, $reflection);
                    break;
                case self::AUTHORIZATION_MIDDLEWARE:
                    new AuthorizationMiddleware($router, $responder, $properties, $reflection);
                    break;
            }
        }

        foreach ($config->getControllers() as $controller) {
            switch ($controller) {
                case self::RECORDS_CONTROLLER:
                    $records = new RecordService($db, $reflection);
                    new RecordController($router, $responder, $records);
                    break;
                case self::COLUMNS_CONTROLLER:
                    $definition = new DefinitionService($db, $reflection);
                    new ColumnController($router, $responder, $reflection, $definition);
                    break;
                case self::CACHE_CONTROLLER:
                    new CacheController($router, $responder, $cache);
                    break;
                case self::OPEN_API_CONTROLLER:
                    $openApi = new OpenApiService($reflection);
                    new OpenApiController($router, $responder, $openApi);
                    break;
            }
        }

        $this->_router = $router;
        $this->_responder = $responder;
        $this->_debug = $config->getDebug();
    }

    public function handle(Request $request = null)    {
        $response = null;
        try {
            $response = $this->_router->route($request);
        } catch (\Throwable $e) {
            if ($e instanceof \PDOException) {
                if (strpos(strtolower($e->getMessage()), 'duplicate') !== false) {
                    return $this->_responder->error(ErrorCode::DUPLICATE_KEY_EXCEPTION, '');
                }
                if (strpos(strtolower($e->getMessage()), 'default value') !== false) {
                    return $this->_responder->error(ErrorCode::DATA_INTEGRITY_VIOLATION, '');
                }
                if (strpos(strtolower($e->getMessage()), 'allow nulls') !== false) {
                    return $this->_responder->error(ErrorCode::DATA_INTEGRITY_VIOLATION, '');
                }
                if (strpos(strtolower($e->getMessage()), 'constraint') !== false) {
                    return $this->_responder->error(ErrorCode::DATA_INTEGRITY_VIOLATION, '');
                }
            }
            $response = $this->_responder->error(ErrorCode::ERROR_NOT_FOUND, $e->getMessage());
            if ($this->_debug) {
                $response->_addHeader('X-Debug-Info', 'Exception in ' . $e->getFile() . ' on line ' . $e->getLine());
            }
        }
        return $response;
    }

}