<?php
namespace Evie\Rest\System\Middleware;

use Evie\Rest\System\Middleware\Base\Middleware;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Request;

class BasicAuthMiddleware extends Middleware    {

    private function _isAllowed($username = null, $password = null, array &$passwords = []) {
        $hash = isset($passwords[$username]) ? $passwords[$username] : false;
        if ($hash && password_verify($password, $hash)) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $passwords[$username] = password_hash($password, PASSWORD_DEFAULT);
            }
            return true;
        }
        return false;
    }

    private function _authenticate($username = null, $password = null, $passwordFile = null) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user']) && $_SESSION['user'] == $username) {
            return true;
        }
        $passwords = $this->_readPasswords($passwordFile);
        $allowed = $this->_isAllowed($username, $password, $passwords);
        if ($allowed) {
            $_SESSION['user'] = $username;
        }
        $this->_writePasswords($passwordFile, $passwords);
        return $allowed;
    }

    private function _readPasswords($passwordFile = null)   {
        $passwords = [];
        $passwordLines = file($passwordFile);
        foreach ($passwordLines as $passwordLine) {
            if (strpos($passwordLine, ':') !== false) {
                list($username, $hash) = explode(':', trim($passwordLine), 2);
                if (strlen($hash) > 0 && $hash[0] != '$') {
                    $hash = password_hash($hash, PASSWORD_DEFAULT);
                }
                $passwords[$username] = $hash;
            }
        }
        return $passwords;
    }

    private function _writePasswords($passwordFile = null, array $passwords = [])   {
        $success = false;
        $passwordFileContents = '';
        foreach ($passwords as $username => $hash) {
            $passwordFileContents .= "$username:$hash\n";
        }
        if (file_get_contents($passwordFile) != $passwordFileContents) {
            $success = file_put_contents($passwordFile, $passwordFileContents) !== false;
        }
        return $success;
    }

    public function handle(Request $request = null)    {
        $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
        $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
        $passwordFile = $this->getProperty('passwordFile', '.htpasswd');
        if (!$username) {
            $response = $this->responder->error(ErrorCode::AUTHORIZATION_REQUIRED, $username);
            $realm = $this->getProperty('realm', 'Username and password required');
            $response->addHeader('WWW-Authenticate', "Basic realm=\"$realm\"");
        } elseif (!$this->_authenticate($username, $password, $passwordFile)) {
            $response = $this->responder->error(ErrorCode::ACCESS_DENIED, $username);
        } else {
            $response = $this->next->handle($request);
        }
        return $response;
    }
}