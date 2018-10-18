<?php
namespace Evie\Rest\System;

class Config {

    const DEFAULT_DRIVER = 'mysql';
    const PG_SQL_DRIVER = 'pg_sql';
    const SQL_SRV_DRIVER = 'sql_srv';

    const DEFAULT_MYSQL_PORT = 3306;
    const DEFAULT_PG_SQL_PORT = 5432;
    const DEFAULT_SQL_SRV_PORT = 1433;

    const DEFAULT_DRIVER_HOST = 'localhost';

    const INVALID_KEY_EXCEPTION = 'Configuration has invalid value: ';

    private $_values = [
        'driver'        => null,
        'host'          => 'localhost',
        'port'          => null,
        'username'      => null,
        'password'      => null,
        'database'      => null,
        'middleware'    => 'cors',
        'controllers'   => [
            'records',
            'columns',
            'cache',
            'openapi'
        ],
        'cacheType'     => 'TempFile',
        'cachePath'     => '',
        'cacheTime'     => 10,
        'debug'         => false
    ];

    private function _getDefaultDriver(array $values = [])    {
        return isset($values['driver'])
            ? $values['driver']
            : self::DEFAULT_DRIVER;
    }

    private function _getDefaultPort($driver = null)  {
        $port = self::DEFAULT_DRIVER;
        switch ($driver)   {
            case self::PG_SQL_DRIVER:   $port = self::DEFAULT_PG_SQL_PORT;  break;
            case self::SQL_SRV_DRIVER:  $port = self::DEFAULT_SQL_SRV_PORT; break;
        }
        return $port;
    }

    private function _getDriverHost($driver = null)    {
        $host = self::DEFAULT_DRIVER_HOST;
        switch ($driver)    {
            case self::DEFAULT_DRIVER:  $host = self::DEFAULT_DRIVER_HOST;  break;
            case self::PG_SQL_DRIVER:   $host = self::DEFAULT_DRIVER_HOST;  break;
            case self::SQL_SRV_DRIVER:  $host = self::DEFAULT_DRIVER_HOST;  break;
        }
        return $host;
    }

    private function _getDriverDefaults($driver = null)   {
        $driver = is_null( $driver ) ? self::DEFAULT_DRIVER : $driver;
        return [
            'driver' => $driver,
            'host'   => $this->_getDriverHost($driver),
            'port'   => $this->_getDefaultPort($driver)
        ];
    }

    private function _parseMiddleware(array $values = [])    {
        $newValues = [];
        $properties = [];
        $middleware = array_map('trim', explode(',', $values['middleware']));
        foreach($middleware as $middle)  {
            $properties[$middle] = [];
        }
        foreach($values as $key => $value)  {
            if(strpos($key, '.') === false) {
                $newValues[$key] = $value;
            } else {
                list($middle, $key2) = explode('.', $key, 2);
                if(isset($properties[$middle])) {
                    $properties[$middle][$key2] = $value;
                } else {
                    throw \Exception(self::INVALID_KEY_EXCEPTION . $key);
                }
            }
        }
        $newValues['middleware'] = $properties;
        return $newValues;
    }

    public function __construct(array $values = [])   {
        $driver     = $this->_getDefaultDriver($values);
        $defaults   = $this->_getDriverDefaults($driver);
        $newValues  = array_merge($this->_values, $defaults, $values);
        $newValues  = $this->_parseMiddleware($newValues);
        $difference = array_diff_key($newValues, $this->_values);
        if(!empty($difference)) {
            $key = array_keys($difference)[0];
            throw new \Exception(self::INVALID_KEY_EXCEPTION . $key);
        }
        $this->_values = $newValues;
    }

    public function getDriver() {
        return $this->_values['driver'];
    }

    public function getHost()   {
        return $this->_values['host'];
    }

    public function getPort()  {
        return $this->_values['port'];
    }

    public function getUsername()  {
        return $this->_values['username'];
    }

    public function getPassword()  {
        return $this->_values['password'];
    }

    public function getDatabase()  {
        return $this->_values['database'];
    }

    public function getMiddleware() {
        return $this->_values['middleware'];
    }

    public function getControllers()    {
        return $this->_values['controllers'];
    }

    public function getCacheType()  {
        return $this->_values['cacheType'];
    }

    public function getCachePath()  {
        return $this->_values['cachePath'];
    }

    public function getCacheTime()  {
        return $this->_values['cacheTime'];
    }

    public function getDebug()  {
        return $this->_values['debug'];
    }

}