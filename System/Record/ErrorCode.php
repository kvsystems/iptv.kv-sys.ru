<?php
namespace Evie\Rest\System\Record;

use Evie\Rest\System\Response;

class ErrorCode {

    const ERROR_NOT_FOUND           = 9999;
    const ROUTE_NOT_FOUND           = 1000;
    const TABLE_NOT_FOUND           = 1001;
    const ARGUMENT_COUNT_MISMATCH   = 1002;
    const RECORD_NOT_FOUND          = 1003;
    const ORIGIN_FORBIDDEN          = 1004;
    const COLUMN_NOT_FOUND          = 1005;
    const TABLE_ALREADY_EXISTS      = 1006;
    const COLUMN_ALREADY_EXISTS     = 1007;
    const HTTP_MESSAGE_NOT_READABLE = 1008;
    const DUPLICATE_KEY_EXCEPTION   = 1009;
    const DATA_INTEGRITY_VIOLATION  = 1010;
    const AUTHORIZATION_REQUIRED    = 1011;
    const ACCESS_DENIED             = 1012;
    const INPUT_VALIDATION_FAILED   = 1013;
    const OPERATION_FORBIDDEN       = 1014;

    private $_code      = null;
    private $_message   = null;
    private $_status    = null;

    private $_values = [
        9999 => ["%s", Response::INTERNAL_SERVER_ERROR],
        1000 => ["Route '%s' not found", Response::NOT_FOUND],
        1001 => ["Table '%s' not found", Response::NOT_FOUND],
        1002 => ["Argument count mismatch in '%s'", Response::NO_PROCESSABLE_ENTITY],
        1003 => ["Record '%s' not found", Response::NOT_FOUND],
        1004 => ["Origin '%s' is forbidden", Response::FORBIDDEN],
        1005 => ["Column '%s' not found", Response::NOT_FOUND],
        1006 => ["Table '%s' already exists", Response::CONFLICT],
        1007 => ["Column '%s' already exists", Response::CONFLICT],
        1008 => ["Cannot read HTTP message", Response::NO_PROCESSABLE_ENTITY],
        1009 => ["Duplicate key exception", Response::CONFLICT],
        1010 => ["Data integrity violation", Response::CONFLICT],
        1011 => ["Authorization required", Response::UNAUTHORIZED],
        1012 => ["Access denied for '%s'", Response::FORBIDDEN],
        1013 => ["Input validation failed for '%s'", Response::NO_PROCESSABLE_ENTITY],
        1014 => ["Operation forbidden", Response::FORBIDDEN],
    ];

    public function __construct($code = 0)  {
        if (!isset($this->_values[$code])) {
            $code = 9999;
        }
        $this->_code = $code;
        $this->_message = $this->_values[$code][0];
        $this->_status = $this->_values[$code][1];
    }

    public function getCode()   {
        return $this->_code;
    }

    public function getMessage($argument = null)    {
        return sprintf($this->_message, $argument);
    }

    public function getStatus() {
        return $this->_status;
    }

}