<?php
namespace Evie\Rest\System;

class Request {

    const DEFAULT_METHOD   = 'GET';
    const DEFAULT_PATH     = '/';
    const DEFAULT_QUERY    = '';
    const DEFAULT_PROTOCOL = 'HTTP_';
    const INPUT_THREAD     = 'php://input';
    const JSON_OPEN        = '[';
    const JSON_INNER       = '{';
    const NULL_THREAD      = '__is_null';

    private $_method        = null;
    private $_path          = null;
    private $_segments      = null;
    private $_params        = null;
    private $_body          = null;
    private $_headers       = null;
    private $_performance   = null;

    private function _parseMethod($method = null) {
        if( !$method )  {
            $method = isset($_SERVER['REQUEST_METHOD'])
                ? $_SERVER['REQUEST_METHOD']
                : self::DEFAULT_METHOD;
        }
        $this->_method = $method;
    }

    private function _parsePath($path = null)   {
        if( !$path )    {
            $path = isset($_SERVER['REQUEST_URI'])
                ? $_SERVER['REQUEST_URI']
                : self::DEFAULT_PATH;
        }
        $this->_path = $path;
        $this->_segments = explode(self::DEFAULT_PATH, $path);
    }

    private function _parseParams($query = null) {
        if(!$query) {
            $query = isset($_SERVER['QUERY_STRING'])
                ? $_SERVER['QUERY_STRING']
                : self::DEFAULT_QUERY;
        }
        $query = str_replace('][]=', ']=', str_replace('=', '[]=', $query));
        parse_str($query, $this->_params);
    }

    private function _parseHeaders($headers = null)    {
        if(!$headers)   {
            $headers = [];
            if(!$this->_performance) {
                foreach($_SERVER as $name => $value)    {
                    if(substr($name, 0, 5) == self::DEFAULT_PROTOCOL)   {
                        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name,5)))));
                        $headers[$key] = $value;
                    }
                }
            }
        }
        $this->_headers = $headers;
    }

    private function _parseBody($body = null)   {
        if(!$body)  {
            $body = file_get_contents(self::INPUT_THREAD);
        }
        $this->_body = $body;
    }

    public function __construct($method = null, $path = null, $query = null, $headers = null, $body = null, $performance = true)   {
        $this->_parseMethod($method);
        $this->_parsePath($path);
        $this->_parseParams($query);
        $this->_parseHeaders($headers);
        $this->_parseBody($body);
        $this->_performance = $performance;
    }

    public function getMethod() {
        return $this->_method;
    }

    public function getPath()   {
        return $this->_path;
    }

    public function getPathSegment($part = null)    {
        return $part < 0 || $part >= count($this->_segments)
            ? '' : $this->_segments[$part];
    }

    public function getParams() {
        return $this->_params;
    }

    public function getBody()   {
        $body = $this->_body;
        $first = substr($body, 0, 1);
        if($first == self::JSON_OPEN || $first == self::JSON_INNER) {
            $body = json_decode($body);
            $causeCode = json_last_error();
            if($causeCode !== JSON_ERROR_NONE){
                return null;
            }
        } else {
            parse_str($body, $input);
            foreach($input as $key => $value) {
                if(substr($key, -9) == self::NULL_THREAD)   {
                    $input[substr($key, 0, -9)] = null;
                    unset($input[$key]);
                }
            }
            $body = (object) $input;
        }
        return $body;
    }

    public function addHeader($key = null, $value = null) {
        if($key && $value)  {
            $this->_headers[$key] = $value;
        }
    }

    public function getHeader($key = null) {
        if(isset($this->_headers[$key]))    {
            return $this->_headers[$key];
        }
        if($this->_performance){
            $serverKey = self::DEFAULT_PROTOCOL . strtoupper(str_replace('_', '-', $key));
            if(isset($_SERVER[$serverKey])) {
                return $_SERVER[$serverKey];
            }
        }
        return self::DEFAULT_QUERY;
    }

    public function getHeaders()    {
        return $this->_headers;
    }

    public static function fromString($request = null) {
        $parts = explode("\n\n", trim($request), 2);
        $head = $parts[0];
        $body = isset($parts[1]) ? $parts[1] : null;
        $lines = explode("\n", $head);
        $line = explode(' ', trim(array_shift($lines)), 2);
        $method = $line[0];
        $url = isset($line[1]) ? $line[1] : '';
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $headers = array();
        foreach ($lines as $line) {
            list($key, $value) = explode(':', $line, 2);
            $headers[$key] = trim($value);
        }
        return new Request($method, $path, $query, $headers, $body);
    }

}