<?php

namespace Galette\Core;

use Galette\Core\GaletteCacheArray;

//voir https://dev.to/he110/boosting-up-php-project-with-cache-16hi
class CacheData
{
    private static ?GaletteCacheArray $cache = null;
    private static array $objectDependencies = [];

    //instancier le cache au 1er usage
    public static function start()
    {     //ACPU ou Pool ou autre si dispo
        //self::$cache = new  ArrayCachePool();
        if(!self::$cache) {

            self::$cache = new GaletteCacheArray();

            $deps  = self::$cache->get('dependencies');
            self::$objectDependencies = $deps ? $deps : [];

            //TestCache();
        }
    }

    /*
    *   @param string $name : nom unique, par exemple self::class, MyClass::class, 'myquery'
    *   @param integer $id : SQL primary key
    *   @param array $dependencies : à partir de quoi la requete à été créée (jointures) [ self::class, Contribution::class, Constribution::TABLE]
    *   @param function $fctSource : valeur issue de la requete SQL
    *   @param function $fctFromCache : appelée si la valeur est issue du cache
    *
    *   @return mixed : retour de la requete ou du cache
    */
    public static function get($name, ?int $id, ?array $dependencies = [], $fctSource, $fctFromCache = null): mixed
    {
        $start = microtime(true);

        if($dependencies == null || count($dependencies) < 1) {
            $dependencies = [$name];
        }

        self::start();
        $cacheKey =  self::getUName($name, $id) ;

        // Trying to get the value from the cache
        $data = self::$cache->get($cacheKey);
        if($data === null) {
            //This data is not available
            $data = $fctSource();

            //Tracking dependencies by class names
            foreach($dependencies as $dep) {
                $depKey = $id !== null ? "{$dep}#{$id}" : $dep;
                if(!array_key_exists($dep, self::$objectDependencies)) {
                    self::$objectDependencies[$depKey] = [$cacheKey];
                } else {
                    self::$objectDependencies[$depKey][] =  $cacheKey;
                }

            }

            self::$cache->set($cacheKey, $data);
            self::$cache->set('dependencies', self::$objectDependencies);
        } elseif($fctFromCache != null) {
            $fctFromCache($data);
        }

        $time =  microtime(true) - $start;

        GaletteCacheArray::logTime('CacheData::get() - '.$cacheKey, $time);

        return $data;
    }



    public static function notifyChange(string $dependency, int $id = -1): void
    {
        self::start();
        if (array_key_exists($dependency, self::$objectDependencies)) {
            foreach(self::$objectDependencies[$dependency] as $cacheKey) {
                self::$cache->delete($cacheKey);
            }
        }
        $depKey = "{$dependency}#{$id}";
        if (array_key_exists($depKey, self::$objectDependencies)) {
            foreach(self::$objectDependencies[$depKey] as $cacheKey) {
                self::$cache->delete($cacheKey);
            }
        }
    }

    public static function invalidate(string $name, int $id = -1): void
    {
        self::start();
        $cacheKey = self::getUName($name, $id) ;

        self::$cache->delete($cacheKey);
    }

    private static function getUName($name, ?int $id): string
    {
        $name = str_replace('\\', '/', $name);
        $cacheKey =  $name;
        if($id !== null) {
            $cacheKey .= "#$id";
        }
        return $cacheKey;
    }

}


function TestCache()
{
    //Cas simple, récupérer un objet issu d'une table SQL par son id
    $data = CacheData::get(Contribution::class, 456, null, function () {
        //SELECT WHERE
        return 4321;
    });

    $cachedData = CacheData::get(Contribution::class, 456, null, function () {
        assert(true);
        return 0;
    });
    assert($data != $cachedData);

    //Récupérer une donnée qui peut être issue d'une requete SQL complexe type JOIN
    $data = CacheData::get('Adherent::functionCountContributions', 123, [Contribution::class, Adherent::class], function () {
        return 4321;
    });

    $cachedData = CacheData::get('Adherent::functionCountContributions', 123, [Contribution::class, Adherent::class], function () {
        assert(true);
        return 0;
    });
    assert($data != $cachedData);

    //Notifier le cache qu'une donnée n'est plus valide après un SQL Insert
    CacheData::notifyChange(Contribution::class);
    CacheData::notifyChange(Contribution::class, 123);
}
