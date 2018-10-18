<?php
namespace Evie\Rest\System\Middleware;

use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Request;

class FirewallMiddleware extends Middleware {

    private function _ipMatch($ip = null, $cIdr = null)  {
        if (strpos($cIdr, '/') !== false) {
            list($subnet, $mask) = explode('/', trim($cIdr));
            if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                return true;
            }
        } else {
            if (ip2long($ip) == ip2long($cIdr)) {
                return true;
            }
        }
        return false;
    }

    private function _isIpAllowed($ipAddress = null, $allowedIpAddresses = null)    {
        foreach (explode(',', $allowedIpAddresses) as $allowedIp) {
            if ($this->_ipMatch($ipAddress, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    public function handle(Request $request = null)    {
        $reverseProxy = $this->getProperty('reverseProxy', '');
        if ($reverseProxy) {
            $ipAddress = array_pop(explode(',', $request->getHeader('X-Forwarded-For')));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = '127.0.0.1';
        }
        $allowedIpAddresses = $this->getProperty('allowedIpAddresses', '');
        if (!$this->_isIpAllowed($ipAddress, $allowedIpAddresses)) {
            $response = $this->responder->error(ErrorCode::ACCESS_DENIED, $ipAddress);
        } else {
            $response = $this->next->handle($request);
        }
        return $response;
    }
}