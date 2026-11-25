<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Analog\Analog;
use FilesystemIterator;
use Galette\Core\CacheManager;
use Galette\Tests\BaseGaletteTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function Safe\chmod;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\scandir;
use function Safe\symlink;
use function Safe\unlink;

/**
 * Cache manager tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CacheManagerTest extends BaseGaletteTestCase
{
    private string $root;
    private string $versions;
    private string $current;
    private string $outside;

    /**
     * Build a cache tree, with a previous version and a file outside of it
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->root = GALETTE_DATA_PATH . 'cachemanager-' . bin2hex(random_bytes(4));
        $this->versions = $this->root . '/cache';
        $this->current = $this->versions . '/v1.4.0';
        $this->outside = $this->root . '/outside';

        mkdir($this->current . '/templates/ab', 0o755, recursive: true);
        mkdir($this->current . '/nominatim', 0o755, recursive: true);
        mkdir($this->current . '/debugbar/sub', 0o755, recursive: true);
        mkdir($this->current . '/myplugin', 0o755, recursive: true);
        mkdir($this->versions . '/v1.3.3/templates', 0o755, recursive: true);
        mkdir($this->versions . '/notaversion', 0o755, recursive: true);
        mkdir($this->outside, 0o755, recursive: true);

        file_put_contents($this->current . '/templates/ab/one.php', '12345');
        file_put_contents($this->current . '/templates/ab/two.php', '123');
        file_put_contents($this->current . '/nominatim/town.json', '{}');
        file_put_contents($this->current . '/debugbar/.gitignore', "*\n!.gitignore\n");
        file_put_contents($this->current . '/debugbar/request.json', '{}');
        file_put_contents($this->current . '/debugbar/sub/.hidden', 'nested');
        file_put_contents($this->versions . '/v1.3.3/templates/.gitignore', '*');
        file_put_contents($this->current . '/releases.cache', 'releases');
        file_put_contents($this->current . '/' . str_repeat('a1', 16) . '.cache', 'news');
        file_put_contents($this->current . '/dark.css', 'body{}');
        file_put_contents($this->current . '/myplugin/data.txt', 'plugin');
        file_put_contents($this->current . '/.htaccess', 'Require all denied');
        file_put_contents($this->versions . '/v1.3.3/templates/old.php', 'old');
        file_put_contents($this->versions . '/readme.txt', 'readme');
        file_put_contents($this->versions . '/notaversion/keep.txt', 'keep');
        file_put_contents($this->outside . '/secret.txt', 'secret');
        file_put_contents($this->outside . '/nested.txt', 'nested');

        //links must be removed, never followed
        symlink($this->outside, $this->current . '/linked');
        symlink($this->outside . '/nested.txt', $this->current . '/templates/ab/link.php');
    }

    /**
     * Remove the cache tree
     */
    public function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            ($file->isDir() && !$file->isLink()) ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);

        parent::tearDown();
    }

    /**
     * Get the cache manager under test
     */
    private function getManager(): CacheManager
    {
        return new CacheManager($this->current, $this->versions);
    }

    /**
     * Get one entry, by key
     *
     * @param CacheManager $manager Cache manager
     * @param string       $key     Cache key
     *
     * @return array<string, mixed>
     */
    private function getEntry(CacheManager $manager, string $key): array
    {
        foreach ($manager->getEntries() as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }
        $this->fail('No entry for ' . $key);
    }

    /**
     * Every entry is described, and counts what it holds
     */
    public function testEntries(): void
    {
        $manager = $this->getManager();
        $entries = $manager->getEntries();

        $this->assertSame($manager->getKeys(), array_column($entries, 'key'));

        $templates = $this->getEntry($manager, 'templates');
        //the link counts as a file, but its target size does not
        $this->assertSame(3, $templates['files']);
        $this->assertSame(8, $templates['size']);
        $this->assertNotNull($templates['mtime']);

        $this->assertSame(['releases.cache'], $this->getEntry($manager, 'releases')['paths']);
        $this->assertSame(1, $this->getEntry($manager, 'news')['files']);
        $this->assertTrue($this->getEntry($manager, 'nominatim')['sensitive']);
        $this->assertFalse($templates['sensitive']);

        $other = $this->getEntry($manager, CacheManager::OTHER);
        //.htaccess protects the directory, it is not part of any cache
        $this->assertSame(['linked', 'myplugin'], $other['paths']);

        $this->assertSame(['v1.3.3'], $this->getEntry($manager, CacheManager::OLD_VERSIONS)['paths']);

        //the top level dot file is not counted, a nested one is
        $this->assertSame(2, $this->getEntry($manager, 'debugbar')['files']);

        $empty = $this->getEntry($manager, 'pdf_contribs');
        $this->assertSame(0, $empty['files']);
        $this->assertNull($empty['mtime']);
    }

    /**
     * Clearing one cache leaves the others alone, and keeps its directory
     */
    public function testClearOne(): void
    {
        $manager = $this->getManager();

        $this->assertTrue($manager->clear('templates'));
        $this->assertSame([], $manager->getErrors());

        $this->assertDirectoryExists($this->current . '/templates');
        $this->assertSame([], array_diff(scandir($this->current . '/templates'), ['.', '..']));
        $this->assertFileExists($this->outside . '/nested.txt');
        $this->assertFileExists($this->current . '/releases.cache');
        $this->assertFileExists($this->current . '/nominatim/town.json');

        $this->assertTrue($manager->clear('releases'));
        $this->assertFileDoesNotExist($this->current . '/releases.cache');
        $this->assertFileExists($this->current . '/dark.css');
    }

    /**
     * Clearing everything spares sensitive caches, previous versions, and links targets
     */
    public function testClearAll(): void
    {
        $manager = $this->getManager();
        $before = array_column($manager->getEntries(), 'files', 'key');

        $this->assertTrue($manager->clear(CacheManager::ALL), implode(', ', $manager->getErrors()));

        foreach ($manager->getEntries() as $entry) {
            if ($entry['sensitive'] || $entry['key'] === CacheManager::OLD_VERSIONS) {
                $this->assertSame($before[$entry['key']], $entry['files'], $entry['key'] . ' has been cleared');
            } else {
                $this->assertSame(0, $entry['files'], $entry['key'] . ' has not been cleared');
            }
        }

        $this->assertFileExists($this->current . '/.htaccess');
        $this->assertFileExists($this->current . '/nominatim/town.json');
        $this->assertDirectoryExists($this->current . '/myplugin');
        $this->assertFalse(is_link($this->current . '/linked'));
        $this->assertFileExists($this->outside . '/secret.txt');
        $this->assertFileExists($this->outside . '/nested.txt');
        $this->assertDirectoryExists($this->versions . '/v1.3.3');
    }

    /**
     * Sensitive caches are cleared on their own, keeping their directory dot files
     */
    public function testClearSensitive(): void
    {
        $manager = $this->getManager();

        $this->assertTrue($manager->clear('debugbar'));
        $this->assertSame(0, $this->getEntry($manager, 'debugbar')['files']);
        //dot files protecting a kept directory stay, nested ones go
        $this->assertFileExists($this->current . '/debugbar/.gitignore');
        $this->assertDirectoryDoesNotExist($this->current . '/debugbar/sub');
        $this->assertFileExists($this->current . '/nominatim/town.json');
    }

    /**
     * Previous versions caches are removed, nothing else around them
     */
    public function testClearOldVersions(): void
    {
        $manager = $this->getManager();

        //named after a version, but not as GALETTE_CACHE_DIR does
        mkdir($this->versions . '/v1.3 backups', 0o755);
        mkdir($this->versions . '/v1.3.3.old', 0o755);
        mkdir($this->versions . '/v1.2-dev', 0o755);

        $this->assertTrue($manager->clear(CacheManager::OLD_VERSIONS));
        $this->assertDirectoryDoesNotExist($this->versions . '/v1.3.3');
        $this->assertDirectoryDoesNotExist($this->versions . '/v1.2-dev');
        $this->assertDirectoryExists($this->versions . '/v1.3 backups');
        $this->assertDirectoryExists($this->versions . '/v1.3.3.old');
        $this->assertDirectoryExists($this->current);
        $this->assertFileExists($this->current . '/releases.cache');
        $this->assertFileExists($this->versions . '/readme.txt');
        $this->assertFileExists($this->versions . '/notaversion/keep.txt');
    }

    /**
     * An unreadable directory hides nothing else, and fails its clearing
     */
    public function testUnreadableDirectory(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('root reads every directory.');
        }
        $manager = $this->getManager();

        chmod($this->current . '/myplugin', 0o000);
        chmod($this->versions, 0o300);
        try {
            $this->assertSame($manager->getKeys(), array_column($manager->getEntries(), 'key'));
            $this->assertSame(3, $this->getEntry($manager, 'templates')['files']);
            $this->assertSame([], $manager->getErrors());

            $this->assertFalse($manager->clear(CacheManager::OLD_VERSIONS));
            $this->assertCount(1, $manager->getErrors());
        } finally {
            chmod($this->versions, 0o755);
            chmod($this->current . '/myplugin', 0o755);
        }
        $this->assertDirectoryExists($this->versions . '/v1.3.3');

        $this->expectLogEntry(Analog::WARNING, 'Unable to inspect cache ' . $this->current . '/myplugin');
        $this->expectLogEntry(Analog::WARNING, 'Unable to list cache directory ' . $this->versions);
        $this->expectLogEntry(Analog::WARNING, 'Cache "old_versions" cleared with errors.');
    }

    /**
     * Unknown keys are refused
     */
    public function testUnknownKey(): void
    {
        $manager = $this->getManager();

        $this->assertFalse($manager->clear('../outside'));
        $this->assertSame(['Unknown cache.'], $manager->getErrors());
        $this->assertFileExists($this->current . '/releases.cache');
    }

    /**
     * Without a versions directory, previous versions are not looked at
     */
    public function testNoVersionsDirectory(): void
    {
        $manager = new CacheManager($this->current);

        $this->assertNotContains(CacheManager::OLD_VERSIONS, $manager->getKeys());
        $this->assertFalse($manager->clear(CacheManager::OLD_VERSIONS));

        $this->assertTrue($manager->clear(CacheManager::ALL));
        $this->assertDirectoryExists($this->versions . '/v1.3.3');
    }

    /**
     * A missing cache directory is just empty
     */
    public function testMissingDirectory(): void
    {
        $manager = new CacheManager($this->root . '/nowhere');

        foreach ($manager->getEntries() as $entry) {
            $this->assertSame(0, $entry['files']);
        }
        $this->assertTrue($manager->clear(CacheManager::ALL));
    }
}
