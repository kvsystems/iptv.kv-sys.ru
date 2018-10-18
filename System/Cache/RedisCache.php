<?php
namespace Evie\Rest\System\Cache;

class RedisCache implements ICache  {

    const LOCAL_HOST    = '127.0.0.1';
    const REDIS_CONNECT = 'pconnect';

    protected $prefix   = null;
    protected $redis    = null;

    public function __construct($prefix = null, $config = null)   {
        $this->prefix = $prefix;
        if ($config == '') {
            $config = self::LOCAL_HOST;
        }
        $params = explode(':', $config, 6);
        if (isset($params[3])) {
            $params[3] = null;
        }
        $this->redis = new \Redis();
        call_user_func_array(array($this->redis, self::REDIS_CONNECT), $params);
    }

    public function set($key = null, $value = null, $ttl = 0)   {
        return $this->redis->set($this->prefix . $key, $value, $ttl);
    }

    public function get($key = null)    {
        return $this->redis->get($this->prefix . $key) ?: '';
    }

    public function clear() {
        return $this->redis->flushDb();
    }

}