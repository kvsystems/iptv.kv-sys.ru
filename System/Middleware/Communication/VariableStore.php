<?php
namespace Evie\Rest\System\Middleware\Communication;

class VariableStore    {

    public static $values = array();

    public static function get($key = null) {
        if (isset(self::$values[$key])) {
            return self::$values[$key];
        }
        return null;
    }

    public static function set($key = null, $value = null)  {
        self::$values[$key] = $value;
    }

}