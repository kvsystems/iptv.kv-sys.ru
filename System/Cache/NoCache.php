<?php
namespace Evie\Rest\System\Cache;

class NoCache implements ICache {

    public function __construct(){}

    public function set($key = null, $value = null, $ttl = 0)   {
        return true;
    }

    public function get($key = null)    {
        return '';
    }

    public function clear() {
        return true;
    }

}