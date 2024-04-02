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
    *   @param string $name : nom unique, par exemple MyClass::class, 'myquery'
    *   @param integer $id : primary key, ou null
    *   @param array $dependencies : à partir de quoi la requete à été créée (jointures) [ MyClass::class => ID, MyClass::class (tous les objets de type Object) ], dépendence sur un objet ou une collection d'objets
    *   @param function $fctSource : valeur issue de la requete SQL
    *   @param function $fctFromCache : appelée si la valeur est issue du cache
    *
    *   @return mixed : retour de la requete ou du cache
    */
    public static function get($name, ?int $id, ?array $dependencies, $fctSource, $fctFromCache = null): mixed
    {
        $start = microtime(true);

        if($dependencies == null || count($dependencies) < 1) {
            $dependencies = [$name => $id];
        }

        self::start();
        $cacheKey =  self::getUName($name, $id) ;

        // Trying to get the value from the cache
        $data = self::$cache->get($cacheKey);
        $bCached = $data !== null;
        if($data === null) {
            //This data is not available
            $data = $fctSource();

            //Tracking dependencies by class names
            foreach($dependencies as $depName => $depId) {
                //If ID is null, we add all objects in Object/*, otherwise Object/MyID
                if (is_int($depName)) {
                    $depName = $depId;
                    $depId = null;
                }

                self::addDependency(self::getUName($depName, null), $cacheKey); //Object/*
                self::addDependency(self::getUName($depName, $depId), $cacheKey); //Object/ID
            }

            self::$cache->set($cacheKey, $data);
            self::$cache->set('dependencies', self::$objectDependencies);
        } elseif($fctFromCache != null) {
            $fctFromCache($data);
        }

        $time =  microtime(true) - $start;

        GaletteCacheArray::logTime("CacheData::get() - $cacheKey".($bCached ? ' (in cache)' : ''), $time);

        return $data;
    }

    private static function addDependency($depKey, $cacheKey)
    {
        if($depKey !== $cacheKey) {
            if(!array_key_exists($depKey, self::$objectDependencies)) {
                self::$objectDependencies[$depKey] = [$cacheKey];
            } else {
                if(!in_array($cacheKey, self::$objectDependencies[$depKey])) {
                    self::$objectDependencies[$depKey][] =  $cacheKey;
                }
            }
        }
    }


    public static function notifyChange(string $dependency, ?int $id = null): int
    {
        self::start();
        $ct = 0;
        $depKey = self::getUName($dependency, $id);
        if (array_key_exists($depKey, self::$objectDependencies)) {
            foreach(self::$objectDependencies[$depKey] as $cacheKey) {
                self::$cache->delete($cacheKey);
                $ct++;
            }
        }
        if($id !== null && self::$cache->has($depKey)) {
            self::$cache->delete($depKey);
            $ct++;
        }
        return  $ct;
    }

    //Clear all objects, a collection or an object
    public static function invalidate(?string $name = null, ?int $id = null): void
    {
        self::start();
        if($name) {
            $cacheKey = self::getUName($name, $id) ;
            self::$cache->delete($cacheKey);
        } else {
            self::$cache->clear();
            self::$objectDependencies = [];
        }
    }

    private static function getUName($name, ?int $id = null): string
    {
        //Remove namespaces
        if (($pos = strrpos($name, '\\')) !== false) {
            $name = substr($name, $pos + 1);
        }

        $cacheKey =  $name.'/'.($id !== null ? (int) $id : '*');
        return $cacheKey;
    }

}

function TestCache()
{
    assert_options(ASSERT_ACTIVE, true);
    assert_options(ASSERT_EXCEPTION, true);

    //Vide tout le cache
    CacheData::invalidate();

    //Cas simple, récupérer un objet issu d'une table SQL par son id
    $data = CacheData::get(Contribution::class, 456, null, function () {
        //SELECT WHERE
        return 100;
    });

    $cachedData = CacheData::get(Contribution::class, 456, null, function () {
        assert(false);
        return 0;
    });
    assert($data == $cachedData);

    //Notifier le cache qu'une donnée n'est plus valide après un SQL Insert
    assert(CacheData::notifyChange(Contribution::class, 456) == 1);

    //
    $data = CacheData::get(Contribution::class, 456, null, function () {
        return 101;
    });
    assert($data == 101);


    //Récupérer une donnée qui peut être issue d'une requete SQL complexe type JOIN
    $data = CacheData::get('Adherent::functionCountContributions', 123, [Contribution::class/*sans ID => tous les objets contributions*/, Adherent::class => 1], function () {
        return 103;
    });
    $data2 = CacheData::get('Adherent::functionCountContributions', 124, [Contribution::class/*sans ID => tous les objets contributions*/, Adherent::class => 1], function () {
        return 104;
    });

    $cachedData = CacheData::get('Adherent::functionCountContributions', 123, [Contribution::class => '*', Adherent::class => 1], function () {
        assert(false);
        return 0;
    });
    assert($data == $cachedData);

    //Notifier le cache qu'une donnée n'est plus valide après un SQL Insert
    //Retire functionCountContributions/123 & 124 + Contribution/456
    assert(CacheData::notifyChange(Contribution::class) == 3);


    CacheData::invalidate();
}
