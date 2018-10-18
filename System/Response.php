<?php
namespace Evie\Rest\System;

class Response {

    const OK = 200;
    const UNAUTHORIZED          = 401;
    const FORBIDDEN             = 403;
    const NOT_FOUND             = 404;
    const CONFLICT              = 409;
    const NO_PROCESSABLE_ENTITY = 422;
    const INTERNAL_SERVER_ERROR = 500;

    private $_status    = null;
    private $_headers   = null;
    private $_body      = null;

    private function _parseBody($body = null)   {
        if ($body === '') {
            $this->body = '';
        } else {
            $data = json_encode($body, JSON_UNESCAPED_UNICODE);
            $this->addHeader('Content-Type', 'application/json');
            $this->addHeader('Content-Length', strlen($data));
            $this->_body = $data;
        }
    }

    public function __construct($status = 0, $body = null)  {
        $this->_status = $status;
        $this->_headers = array();
        $this->_parseBody($body);
    }

    public function getStatus() {
        return $this->_status;
    }

    public function getBody()   {
        return $this->_body;
    }

    public function addHeader($key = null, $value = null)   {
        $this->_headers[$key] = $value;
    }

    public function getHeader($key = null)  {
        if (isset($this->_headers[$key])) {
            return $this->_headers[$key];
        }
        return null;
    }

    public function getHeaders()    {
        return $this->_headers;
    }

    public function output()    {
        http_response_code($this->getStatus());
        foreach ($this->_headers as $key => $value) {
            header("$key: $value");
        }
        echo $this->getBody();
    }

    public function toString()  {
        $str = "$this->_status\n";
        foreach ($this->_headers as $key => $value) {
            $str .= "$key: $value\n";
        }
        if ($this->_body !== '') {
            $str .= "\n";
            $str .= "$this->_body\n";
        }
        return $str;
    }

}