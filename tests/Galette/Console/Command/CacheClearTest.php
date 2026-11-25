<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\CacheClear;
use Galette\Core\CacheManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\unlink;

/**
 * Cache clear command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CacheClearTest extends TestCase
{
    private string $cache_dir;
    private CommandTester $tester;

    /**
     * Build a small cache directory
     */
    protected function setUp(): void
    {
        $this->cache_dir = GALETTE_DATA_PATH . 'cacheclear-' . bin2hex(random_bytes(4));
        mkdir($this->cache_dir . '/templates', 0o755, recursive: true);
        file_put_contents($this->cache_dir . '/templates/page.php', 'page');
        file_put_contents($this->cache_dir . '/releases.cache', 'releases');

        $this->tester = new CommandTester(
            new CacheClear(GALETTE_BASE_PATH, new CacheManager($this->cache_dir))
        );
    }

    /**
     * Remove the cache directory
     */
    protected function tearDown(): void
    {
        foreach (['/templates/page.php', '/releases.cache'] as $file) {
            if (file_exists($this->cache_dir . $file)) {
                unlink($this->cache_dir . $file);
            }
        }
        rmdir($this->cache_dir . '/templates');
        rmdir($this->cache_dir);
    }

    /**
     * Listing does not clear anything
     */
    public function testList(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester->execute(['--list' => true]));
        $display = $this->tester->getDisplay();
        $this->assertMatchesRegularExpression('/templates\s+yes\s+1\s/', $display);
        $this->assertMatchesRegularExpression('/releases\s+yes\s+1\s/', $display);
        $this->assertMatchesRegularExpression('/nominatim\s+no\s+0\s/', $display);
        $this->assertFileExists($this->cache_dir . '/releases.cache');
    }

    /**
     * Only the caches given are cleared
     */
    public function testClearSome(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester->execute(['caches' => ['releases']]));
        $this->assertStringContainsString('Cache cleared: releases.', $this->tester->getDisplay());
        $this->assertFileDoesNotExist($this->cache_dir . '/releases.cache');
        $this->assertFileExists($this->cache_dir . '/templates/page.php');
    }

    /**
     * Everything but sensitive caches is cleared by default
     */
    public function testClearAll(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester->execute([]));
        $this->assertStringContainsString('Cache has been cleared.', $this->tester->getDisplay());
        $this->assertStringContainsString('nominatim', $this->tester->getDisplay());
        $this->assertFileDoesNotExist($this->cache_dir . '/releases.cache');
        $this->assertFileDoesNotExist($this->cache_dir . '/templates/page.php');
        $this->assertDirectoryExists($this->cache_dir . '/templates');
    }

    /**
     * Unknown caches are refused before anything is cleared
     */
    public function testUnknownCache(): void
    {
        $this->assertSame(Command::INVALID, $this->tester->execute(['caches' => ['releases', 'nope']]));
        $this->assertStringContainsString('Unknown cache(s): nope.', $this->tester->getDisplay());
        $this->assertFileExists($this->cache_dir . '/releases.cache');
    }
}
