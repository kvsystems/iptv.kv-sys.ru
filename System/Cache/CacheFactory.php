<?php
namespace Evie\Rest\System\Cache;

use Evie\Rest\System\Config;

class CacheFactory  {

    const PREFIX                = 'evie-rest-%s-';
    const TEMP_FILE_TYPE        = 'TempFile';
    const TEMP_FILE_REDIS       = 'Redis';
    const TEMP_FILE_MEMCACHE    = 'Memcache';
    const TEMP_FILE_MEMCACHED   = 'Memcached';

    private static function getPrefix() {
        return sprintf(self::PREFIX, substr(md5(__FILE__), 0, 8));
    }

    public static function create(Config $config)   {
        switch ($config->getCacheType()) {
            case self::TEMP_FILE_TYPE:
                $cache = new TempFileCache(self::getPrefix(), $config->getCachePath());
                break;
            case self::TEMP_FILE_REDIS:
                $cache = new RedisCache(self::getPrefix(), $config->getCachePath());
                break;
            case self::TEMP_FILE_MEMCACHE:
                $cache = new MemcacheCache(self::getPrefix(), $config->getCachePath());
                break;
            case self::TEMP_FILE_MEMCACHED:
                $cache = new MemcachedCache(self::getPrefix(), $config->getCachePath());
                break;
            default:
                $cache = new NoCache();
        }

        return $cache;
    }

}