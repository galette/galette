<?php

namespace Galette\Core;

use Galette\Core\Galette;
use Galette\Features\Cacheable;
use Analog\Analog;

class GaletteCacheArray
{
    use Cacheable;

    private string $cache_filename = 'objects.cache';
    private array $memory = [];

    public function __construct()
    {
        if($this->checkCache()) {
            $this->loadCache();
        }
    }

    public function __destruct()
    {
        /*Pour gagner encore en rapidité, il faudrait sauver le cache une fois la reponse HTTP envoyée ?
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        */
        $this->makeCache();
    }

    //Implementation PSR-16

    // Method for storing data in memory
    public function set(string $key, $value): bool
    {
        $this->memory[$key] = $value;

        return true;
    }

    // Method for getting data from memory
    public function get(string $key)
    {
        return $this->memory[$key] ?? null;
    }

    // Method for deleting data from memory
    public function delete(string $key): bool
    {
        unset($this->memory[$key]);

        return true;
    }

    // Method for checking the availability of key data
    public function has(string $key): bool
    {
        return array_key_exists($key, self::$memory);
    }

    public function clear()
    {
        $this->memory = [];
    }

    protected function prepareForCache(): void
    {
    }

    //voir https://github.com/shabeer-ali-m/php-super-cache/blob/master/src/SuperCache/SuperCache.php
    protected function getDataTocache(): string
    {
        $start = microtime(true);

        if (function_exists('igbinary_serialize')) {
            $ret = igbinary_serialize($this->memory);
        } else {
            $ret = serialize($this->memory);
        }

        return $ret;
    }

    /**
     * Called once cache has been loaded.
     *
     * @param mixed $contents Content from cache
     *
     * @return bool
     */
    protected function cacheLoaded($contents): bool
    {
        $start = microtime(true);
        try {
            if (function_exists('igbinary_serialize')) {
                $this->memory = igbinary_unserialize($contents);
            } else {
                $this->memory = unserialize($contents);
            }

        } catch (\Throwable $e) {
            $this->memory = [];
            return false;
        }

        $time = microtime(true) - $start;

        if (count($this->memory) == 0) {
            return false;
        }


        self::logTime('cacheLoaded()', $time);
        return true;
    }




    /**
     * Complete path to cache file
     *
     * @return string
     */
    protected function getCacheFilename(): string
    {
        return $this->cache_filename;
    }




    public static function logTime($fct, $time)
    {
        $time = round($time * 1000, 3);
        Analog::log(
            "Cache infos : $fct - Exec. time $time ms",
            Analog::DEBUG
        );
    }
}
