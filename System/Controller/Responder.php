<?php
namespace Evie\Rest\System\Controller;

use Evie\Rest\System\Record\Document\ErrorDocument;
use Evie\Rest\System\Record\ErrorCode;
use Evie\Rest\System\Response;

class Responder {

    public function error($error = null, $argument = null, $details = null) {
        $errorCode = new ErrorCode($error);
        $status = $errorCode->getStatus();
        $document = new ErrorDocument($errorCode, $argument, $details);
        return new Response($status, $document);
    }

    public function success($result)    {
        return new Response(Response::OK, $result);
    }

}