<?php
namespace Evie\Rest\System\Cache;

class TempFileCache implements ICache    {

    const SUFFIX = 'cache';

    private $_path      = null;
    private $_segments  = null;

    private function _getFileName($key = null)  {
        $md5 = md5($key);
        $filename = rtrim($this->_path, DS) . DS;
        $i = 0;
        foreach ($this->_segments as $segment) {
            $filename .= substr($md5, $i, $segment) . DS;
            $i += $segment;
        }
        $filename .= substr($md5, $i);
        return $filename;
    }

    private function _filePutContents($filename = null, $string = null) {
        return file_put_contents($filename, $string, LOCK_EX);
    }

    private function _fileGetContents($filename = null) {
        $file = fopen($filename, 'rb');
        if ($file === false) {
            return false;
        }
        $lock = flock($file, LOCK_SH);
        if (!$lock) {
            fclose($file);
            return false;
        }
        $string = '';
        while (!feof($file)) {
            $string .= fread($file, 8192);
        }
        flock($file, LOCK_UN);
        fclose($file);
        return $string;
    }

    private function _getString($filename = null)   {
        $data = $this->_fileGetContents($filename);
        if ($data === false) {
            return '';
        }
        list($ttl, $string) = explode('|', $data, 2);
        if ($ttl > 0 && time() - filemtime($filename) > $ttl) {
            return '';
        }
        return $string;
    }

    private function _clean($path = null, array $segments = [], $len = null, $all = false)  {
        $entries = scandir($path);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $filename = $path . DS . $entry;
            if (count($segments) == 0) {
                if (strlen($entry) != $len) {
                    continue;
                }
                if (is_file($filename)) {
                    if ($all || $this->_getString($filename) == null) {
                        unlink($filename);
                    }
                }
            } else {
                if (strlen($entry) != $segments[0]) {
                    continue;
                }
                if (is_dir($filename)) {
                    $this->_clean($filename, array_slice($segments, 1), $len - $segments[0], $all);
                    rmdir($filename);
                }
            }
        }
    }

    public function __construct($prefix = null, $config = null)   {
        $this->_segments = [];
        if ($config == '') {
            $id = substr(md5(__FILE__), 0, 8);
            $this->_path = sys_get_temp_dir() . DS . $prefix . self::SUFFIX;
        } elseif (strpos($config, PS) === false) {
            $this->_path = $config;
        } else {
            list($path, $segments) = explode(PS, $config);
            $this->_path = $path;
            $this->_segments = explode(',', $segments);
        }
        if (file_exists($this->_path) && is_dir($this->_path)) {
            $this->_clean($this->_path, array_filter($this->_segments), strlen(md5('')), false);
        }
    }

    public function set($key = null, $value = null, $ttl = 0)   {
        $filename = $this->_getFileName($key);
        $dirname = dirname($filename);
        if (!file_exists($dirname)) {
            if (!mkdir($dirname, 0755, true)) {
                return false;
            }
        }
        $string = $ttl . '|' . $value;
        return $this->_filePutContents($filename, $string) !== false;
    }

    public function get($key = null)    {
        $filename = $this->_getFileName($key);
        if (!file_exists($filename)) {
            return '';
        }
        $string = $this->_getString($filename);
        if ($string == null) {
            return '';
        }
        return $string;
    }

    public function clear() {
        if (!file_exists($this->_path) || !is_dir($this->_path)) {
            return false;
        }
        $this->_clean($this->_path, array_filter($this->_segments), strlen(md5('')), true);
        return true;
    }

}