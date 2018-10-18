<?php
namespace Evie\Rest\System\Middleware;

use Evie\Rest\System\Controller\Responder;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Request;
use Evie\Rest\System\Response;
use Evie\Rest\System\Middleware\Base\Middleware;

class CorsMiddleware extends Middleware {

    private function _isOriginAllowed($origin = null, $allowedOrigins = null)   {
        $found = false;
        foreach (explode(',', $allowedOrigins) as $allowedOrigin) {
            $hostname = preg_quote(strtolower(trim($allowedOrigin)));
            $regex = '/^' . str_replace('\*', '.*', $hostname) . '$/';
            if (preg_match($regex, $origin)) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    public function handle(Request $request = null)    {
        $method = $request->getMethod();
        $origin = $request->getHeader('Origin');
        $allowedOrigins = $this->getProperty('allowedOrigins', '*');
        if ($origin && !$this->_isOriginAllowed($origin, $allowedOrigins)) {
            $response = $this->responder->error(ErrorCode::ORIGIN_FORBIDDEN, $origin);
        } elseif ($method == 'OPTIONS') {
            $response = new Response(Response::OK, '');
            $allowHeaders = $this->getProperty('allowHeaders', 'Content-Type, X-XSRF-TOKEN');
            $response->addHeader('Access-Control-Allow-Headers', $allowHeaders);
            $allowMethods = $this->getProperty('allowMethods', 'OPTIONS, GET, PUT, POST, DELETE, PATCH');
            $response->addHeader('Access-Control-Allow-Methods', $allowMethods);
            $allowCredentials = $this->getProperty('allowCredentials', 'true');
            $response->addHeader('Access-Control-Allow-Credentials', $allowCredentials);
            $maxAge = $this->getProperty('maxAge', '1728000');
            $response->addHeader('Access-Control-Max-Age', $maxAge);
        } else {
            $response = $this->next->handle($request);
        }
        if ($origin) {
            $allowCredentials = $this->getProperty('allowCredentials', 'true');
            $response->addHeader('Access-Control-Allow-Credentials', $allowCredentials);
            $response->addHeader('Access-Control-Allow-Origin', $origin);
        }
        return $response;
    }
}