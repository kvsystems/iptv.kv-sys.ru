<?php
namespace Evie\Rest\System\Cache;

class MemcacheCache implements ICache   {

    const LOCAL_HOST    = '127.0.0.1';
    const MEMCACHE_PORT = 11211;

    protected $prefix   = null;
    protected $memcache = null;

    protected function create() {
        return new \Memcache();
    }

    public function __construct($prefix = null, $config = null)   {
        $this->prefix = $prefix;
        if ($config == '') {
            $address = self::LOCAL_HOST;
            $port = self::MEMCACHE_PORT;
        } elseif (strpos($config, ':') === false) {
            $address = $config;
            $port = self::MEMCACHE_PORT;
        } else {
            list($address, $port) = explode(':', $config);
        }
        $this->memcache = $this->create();
        $this->memcache->addServer($address, $port);
    }

    public function set($key = null, $value = null, $ttl = 0)   {
        return $this->memcache->set($this->prefix . $key, $value, 0, $ttl);
    }

    public function get($key = null)    {
        return $this->memcache->get($this->prefix . $key) ?: '';
    }

    public function clear() {
        return $this->memcache->flush();
    }

}