<?php
namespace Evie\Rest\System\Cache;

class MemcachedCache extends MemcacheCache  {

    protected function create() {
        return new \Memcached();
    }

    public function set($key = null, $value = null, $ttl = 0)   {
        return $this->memcache->set($this->prefix . $key, $value, $ttl);
    }

}