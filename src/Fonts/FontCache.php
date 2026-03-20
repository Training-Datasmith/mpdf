<?php

declare (strict_types=1);
namespace Mpdf\Fonts;

use Mpdf\Cache;
class Font_Cache
{
    private $memory_cache = [];
    private $cache;
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }
    public function temp_filename($filename)
    {
        return $this->cache->temp_filename($filename);
    }
    public function has($filename)
    {
        return $this->cache->has($filename);
    }
    public function json_has($filename)
    {
        return isset($this->memory_cache[$filename]) || $this->has($filename);
    }
    public function load($filename)
    {
        return $this->cache->load($filename);
    }
    public function json_load($filename)
    {
        if (isset($this->memory_cache[$filename])) {
            return $this->memory_cache[$filename];
        }
        $this->memory_cache[$filename] = json_decode($this->load($filename), true);
        return $this->memory_cache[$filename];
    }
    public function write($filename, $data)
    {
        return $this->cache->write($filename, $data);
    }
    public function binary_write($filename, $data)
    {
        return $this->cache->write($filename, $data);
    }
    public function json_write($filename, $data)
    {
        return $this->cache->write($filename, json_encode($data));
    }
    public function remove($filename)
    {
        return $this->cache->remove($filename);
    }
    public function json_remove($filename)
    {
        if (isset($this->memory_cache[$filename])) {
            unset($this->memory_cache[$filename]);
        }
        $this->remove($filename);
    }
}