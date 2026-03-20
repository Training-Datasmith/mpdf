<?php

declare (strict_types=1);
namespace Mpdf;

use Directory_Iterator;
class Cache
{
    private $base_path;
    private $cleanup_interval;
    public function __construct($base_path, $cleanup_interval = 3600)
    {
        if (!is_int($cleanup_interval) && false !== $cleanup_interval) {
            throw new \Mpdf\Mpdf_Exception('Cache cleanup interval has to be an integer or false');
        }
        if (!$this->create_base_path($base_path)) {
            throw new \Mpdf\Mpdf_Exception(sprintf('Temporary files directory "%s" is not writable', $base_path));
        }
        $this->base_path = $base_path;
        $this->cleanup_interval = $cleanup_interval;
    }
    protected function create_base_path($base_path)
    {
        if (!file_exists($base_path)) {
            if (!$this->create_directory($base_path)) {
                return false;
            }
        }
        if (!is_writable($base_path) || !is_dir($base_path)) {
            return false;
        }
        return true;
    }
    protected function create_directory($base_path)
    {
        $parent_path = $this->get_existing_parent_directory($base_path);
        $permissions = $this->get_permission($parent_path);
        if (!mkdir($base_path, $permissions, true)) {
            return false;
        }
        /* Check if umask modified the permissions and reset any created directories */
        if (($permissions & ~umask()) !== $permissions) {
            $base_path = realpath($base_path);
            $folders = explode('/', substr($base_path, strlen($parent_path) + 1));
            for ($i = 1, $total = count($folders); $i <= $total; $i++) {
                $path = $parent_path . '/';
                $path .= implode('/', array_slice($folders, 0, $i));
                chmod($path, $permissions);
            }
        }
        return true;
    }
    protected function get_existing_parent_directory($base_path)
    {
        $target_parent = dirname($base_path);
        while ($target_parent !== '.' && !is_dir($target_parent) && dirname($target_parent) !== $target_parent) {
            $target_parent = dirname($target_parent);
        }
        return realpath($target_parent);
    }
    protected function get_permission($base_path, $fallback_permission = 0777)
    {
        if (!is_dir($base_path)) {
            return $fallback_permission;
        }
        $result = fileperms($base_path);
        return $result ? $result & 07777 : $fallback_permission;
    }
    public function temp_filename($filename)
    {
        return $this->get_file_path($filename);
    }
    public function has($filename)
    {
        return file_exists($this->get_file_path($filename));
    }
    public function load($filename)
    {
        return file_get_contents($this->get_file_path($filename));
    }
    public function write($filename, $data)
    {
        $temp_file = tempnam($this->base_path, 'cache_tmp_');
        file_put_contents($temp_file, $data);
        chmod($temp_file, 0664);
        $path = $this->get_file_path($filename);
        rename($temp_file, $path);
        return $path;
    }
    public function remove($filename)
    {
        return unlink($this->get_file_path($filename));
    }
    public function clear_old()
    {
        $iterator = new Directory_Iterator($this->base_path);
        /** @var \DirectoryIterator $item */
        foreach ($iterator as $item) {
            if (!$item->is_dot() && $item->is_file() && !$this->is_dot_file($item) && $this->is_old($item)) {
                unlink($item->get_pathname());
            }
        }
    }
    private function get_file_path($filename)
    {
        return $this->base_path . '/' . $filename;
    }
    private function is_old(Directory_Iterator $item)
    {
        return $this->cleanup_interval && $item->get_m_time() + $this->cleanup_interval < time();
    }
    public function is_dot_file(Directory_Iterator $item)
    {
        return substr($item->get_filename(), 0, 1) === '.';
    }
}