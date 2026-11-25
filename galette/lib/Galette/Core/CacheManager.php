<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

use function Safe\preg_match;
use function Safe\realpath;
use function Safe\rmdir;
use function Safe\scandir;
use function Safe\unlink;

/**
 * Files Galette keeps in its cache directory
 *
 * Every one of them can be thrown away: Galette builds it again when it needs
 * it. Clearing them used to mean removing files by hand on the server, often
 * as another system user than the web server one, which then could neither
 * remove nor rewrite what it had created.
 *
 * Directories are emptied, never removed: some of their producers do not create
 * them again. Previous versions caches, left behind by updates, are removed.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class CacheManager
{
    /** Every cache of the running version, sensitive ones excepted */
    public const string ALL = 'all';
    /** Entries in cache directory no known cache claims, such as plugins ones */
    public const string OTHER = 'other';
    /** Caches of previous Galette versions */
    public const string OLD_VERSIONS = 'old_versions';

    /** Known caches, and the names they use at the root of the cache directory */
    private const array ENTRIES = [
        'templates'     => ['templates'],
        'htmlpurifier'  => ['htmlpurifier'],
        'nominatim'     => ['nominatim'],
        'releases'      => ['releases.cache'],
        'news'          => [],
        'telemetry'     => ['telemetry.cache'],
        'darkcss'       => ['dark.css'],
        'printlogo'     => [],
        'pdf_contribs'  => ['pdf_contribs'],
        'debugbar'      => ['debugbar'],
    ];

    /**
     * Caches whose clearing is not free: external services queried again
     * (nominatim), telemetry possibly sent again, files a post-contribution
     * script may still read, or debug bar records lost. They are only cleared
     * on their own.
     */
    private const array SENSITIVE = ['nominatim', 'telemetry', 'pdf_contribs', 'debugbar'];

    /** Known caches whose names vary, as regular expressions */
    private const array PATTERNS = [
        'news'          => '/^[0-9a-f]{32}\.cache$/',
        //written aside, then renamed
        'darkcss'       => '/^dark\.css\.[0-9a-f]+\.tmp$/',
        'printlogo'     => '/^galette_printlogo_converted\./',
    ];

    /** A previous version cache directory, as named by GALETTE_CACHE_DIR */
    private const string VERSION_PATTERN = '/^v?\d+(\.\d+)+(-?(dev|alpha|beta|rc\d*))?$/';

    private readonly string $cache_dir;
    private readonly ?string $versions_dir;
    /** @var array<int, string> */
    private array $errors = [];

    /**
     * Default constructor
     *
     * @param ?string $cache_dir    Cache directory, GALETTE_CACHE_DIR by default
     * @param ?string $versions_dir Directory holding one cache directory per version;
     *                              by default, the parent of the cache directory
     *                              when it is the one Galette ships with
     */
    public function __construct(?string $cache_dir = null, ?string $versions_dir = null)
    {
        $this->cache_dir = rtrim($cache_dir ?? GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR);

        if ($versions_dir === null && $cache_dir === null) {
            //a cache directory set in local_paths.inc.php may sit anywhere:
            //its siblings are not ours to look at
            $default = $this->resolve(GALETTE_DATA_PATH . 'cache');
            $parent = $this->resolve(dirname($this->cache_dir));
            if ($default !== null && $default === $parent) {
                $versions_dir = $default;
            }
        }
        $this->versions_dir = $versions_dir !== null ? rtrim($versions_dir, DIRECTORY_SEPARATOR) : null;
    }

    /**
     * Keys of everything that can be cleared one by one
     *
     * @return array<int, string>
     */
    public function getKeys(): array
    {
        $keys = array_keys(self::ENTRIES);
        $keys[] = self::OTHER;
        if ($this->versions_dir !== null) {
            $keys[] = self::OLD_VERSIONS;
        }
        return $keys;
    }

    /**
     * Describe every cache, with what it holds right now
     *
     * @return array<int, array{
     *     key: string,
     *     sensitive: bool,
     *     paths: array<int, string>,
     *     files: int,
     *     size: int,
     *     mtime: ?int
     * }>
     */
    public function getEntries(): array
    {
        //listing is not clearing: getErrors() reports the last clear() only
        $errors = $this->errors;
        $entries = [];
        foreach ($this->getKeys() as $key) {
            $paths = $this->getPaths($key);
            $files = 0;
            $size = 0;
            $mtime = null;
            foreach ($paths as $path) {
                //an unreadable directory, or a file removed meanwhile, must not hide the other caches
                try {
                    foreach ($this->getFiles($path) as $file) {
                        ++$files;
                        if (!$file->isLink()) {
                            $size += $file->getSize();
                            $mtime = max($mtime ?? 0, $file->getMTime());
                        }
                    }
                } catch (Throwable $e) {
                    Logs::exception($e, 'Unable to inspect cache ' . $path, Analog::WARNING);
                }
            }

            $entries[] = [
                'key'       => $key,
                'sensitive' => $this->isSensitive($key),
                'paths'     => array_map(basename(...), $paths),
                'files'     => $files,
                'size'      => $size,
                'mtime'     => $mtime,
            ];
        }
        $this->errors = $errors;
        return $entries;
    }

    /**
     * Is clearing a cache more than a slowdown?
     *
     * Sensitive caches, and previous versions ones, are left out of self::ALL.
     *
     * @param string $key Cache key
     */
    public function isSensitive(string $key): bool
    {
        return in_array($key, self::SENSITIVE, strict: true);
    }

    /**
     * Clear one cache, or every cache of the running version but sensitive ones
     *
     * @param string $key Cache key, or self::ALL
     */
    public function clear(string $key): bool
    {
        $this->errors = [];

        if ($key === self::ALL) {
            $keys = array_filter(
                $this->getKeys(),
                fn(string $current): bool => $current !== self::OLD_VERSIONS && !$this->isSensitive($current)
            );
        } elseif (in_array($key, $this->getKeys(), strict: true)) {
            $keys = [$key];
        } else {
            //only a forged request gets here
            $this->errors[] = _T("Unknown cache.");
            return false;
        }

        foreach ($keys as $current) {
            foreach ($this->getPaths($current) as $path) {
                $this->remove($path, keep_directory: $current !== self::OLD_VERSIONS);
            }
        }

        Analog::log(
            sprintf('Cache "%s" cleared%s.', $key, $this->errors === [] ? '' : ' with errors'),
            $this->errors === [] ? Analog::INFO : Analog::WARNING
        );

        return $this->errors === [];
    }

    /**
     * Errors met by the last clear() call
     *
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Paths a cache is made of
     *
     * @param string $key Cache key
     *
     * @return array<int, string>
     */
    private function getPaths(string $key): array
    {
        if ($key === self::OLD_VERSIONS) {
            return $this->getOldVersionsPaths();
        }

        $paths = [];
        foreach ($this->listDirectory($this->cache_dir) as $name) {
            $owner = $this->getOwner($name);
            if ($owner === $key || ($owner === null && $key === self::OTHER)) {
                $paths[] = $this->cache_dir . DIRECTORY_SEPARATOR . $name;
            }
        }
        return $paths;
    }

    /**
     * Known cache a name at the root of the cache directory belongs to
     *
     * @param string $name File or directory name
     */
    private function getOwner(string $name): ?string
    {
        foreach (self::ENTRIES as $key => $names) {
            if (in_array($name, $names, strict: true)) {
                return $key;
            }
        }
        foreach (self::PATTERNS as $key => $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Cache directories of previous versions
     *
     * @return array<int, string>
     */
    private function getOldVersionsPaths(): array
    {
        if ($this->versions_dir === null) {
            return [];
        }

        $current = $this->resolve($this->cache_dir);
        $paths = [];
        foreach ($this->listDirectory($this->versions_dir) as $name) {
            $path = $this->versions_dir . DIRECTORY_SEPARATOR . $name;
            if (
                preg_match(self::VERSION_PATTERN, $name) === 1
                && is_dir($path)
                && !is_link($path)
                && $this->resolve($path) !== $current
            ) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /**
     * Names in a directory, dot files excepted
     *
     * @param string $directory Directory
     *
     * @return array<int, string>
     */
    private function listDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        try {
            $names = scandir($directory);
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to list cache directory ' . $directory, Analog::WARNING);
            //nothing found to remove is not a success
            $this->errors[] = sprintf(
                //TRANS: parameter is a path on the server
                _T('Unable to remove %1$s, check the files permissions.'),
                $directory
            );
            return [];
        }

        //.htaccess and friends protect the directory, they are not cache
        return array_values(array_filter($names, static fn(string $name): bool => !str_starts_with($name, '.')));
    }

    /**
     * Files under a path, links included but never followed
     *
     * @param string $path File or directory
     *
     * @return iterable<SplFileInfo>
     */
    private function getFiles(string $path): iterable
    {
        if (is_link($path) || !is_dir($path)) {
            yield new SplFileInfo($path);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ((!$file->isDir() || $file->isLink()) && !$this->isProtected($file, $path)) {
                yield $file;
            }
        }
    }

    /**
     * Is a file protecting the directory it sits in, rather than cache?
     *
     * Dot files at the top of a cache directory - such as the .gitignore the
     * debug bar writes - are kept while it is emptied, as are those at the
     * root of the cache directory.
     *
     * @param SplFileInfo $file      File to check
     * @param string      $directory Cache directory being walked
     */
    private function isProtected(SplFileInfo $file, string $directory): bool
    {
        return str_starts_with($file->getFilename(), '.')
            && $file->getPath() === rtrim($directory, DIRECTORY_SEPARATOR);
    }

    /**
     * Remove a path
     *
     * @param string $path           File or directory
     * @param bool   $keep_directory Empty a directory rather than removing it
     */
    private function remove(string $path, bool $keep_directory): void
    {
        if (!$this->isContained($path)) {
            $this->errors[] = sprintf(
                //TRANS: parameter is a path on the server
                _T('%1$s is not in the cache directory.'),
                $path
            );
            return;
        }

        try {
            if (is_link($path) || !is_dir($path)) {
                //a link is removed, what it points to is left alone
                unlink($path);
                return;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($keep_directory && $this->isProtected($file, $path)) {
                    continue;
                }
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }

            if (!$keep_directory) {
                rmdir($path);
            }
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to remove ' . $path . ' from cache', Analog::WARNING);
            $this->errors[] = sprintf(
                //TRANS: parameter is a path on the server
                _T('Unable to remove %1$s, check the files permissions.'),
                $path
            );
        }
    }

    /**
     * Is a path one of the cache directory entries, or a previous version one?
     *
     * Paths are built from directories listings, never from user input; this
     * still stands between a mistake and removing anything else.
     *
     * @param string $path Path to check
     */
    private function isContained(string $path): bool
    {
        //the link itself is checked, not what it points to
        $parent = $this->resolve(dirname($path));
        if ($parent === null) {
            return false;
        }

        $allowed = [$this->resolve($this->cache_dir)];
        if ($this->versions_dir !== null) {
            $allowed[] = $this->resolve($this->versions_dir);
        }

        return in_array($parent, array_filter($allowed), strict: true);
    }

    /**
     * Real path, or null when it does not exist
     *
     * @param string $path Path to resolve
     */
    private function resolve(string $path): ?string
    {
        try {
            return realpath($path);
        } catch (Throwable) {
            // no log: a missing path is an expected answer here
            return null;
        }
    }
}
