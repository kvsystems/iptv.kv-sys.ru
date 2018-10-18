<?php
namespace Evie\Rest\System\Middleware\Base;

use Evie\Rest\System\Request;

interface IHandler  {

    public function handle(Request $request);

}