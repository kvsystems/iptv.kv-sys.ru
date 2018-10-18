<?php
namespace Evie\Rest\System\Cache;

interface ICache {

    public function set($key = null, $value = null, $ttl = 0);
    public function get($key = null);
    public function clear();

}