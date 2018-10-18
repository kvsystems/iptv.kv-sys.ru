<?php
namespace Evie\Rest\System\Record\Document;

use Evie\Rest\System\Record\ErrorCode;

class ErrorDocument {

    public $code    = null;
    public $message = null;
    public $details = null;

    public function __construct(ErrorCode $errorCode = null, $argument = null, $details = null) {
        $this->code = $errorCode->getCode();
        $this->message = $errorCode->getMessage($argument);
        $this->details = $details;
    }

    public function getCode()   {
        return $this->code;
    }

    public function getMessage()    {
        return $this->message;
    }

    public function serialize() {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }

    public function jsonSerialize() {
        return array_filter($this->serialize());
    }

}