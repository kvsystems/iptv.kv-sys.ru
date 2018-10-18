<?php
namespace Evie\Rest\System\OpenApi;

use Evie\Rest\System\Column\ReflectionService;

class OpenApiService    {

    private $_reflection = null;

    public function __construct(ReflectionService $reflection = null)   {
        $this->_reflection = $reflection;
    }

}