<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Debug;

use DebugBar\DebugBarException;
use DebugBar\Storage\FileStorage;
use Galette\Debug\SessionStorage;
use PHPUnit\Framework\TestCase;
use RKA\Session;

use function Safe\glob;
use function Safe\rmdir;
use function Safe\unlink;

/**
 * Debug bar session restricted storage tests
 *
 * Both sessions share $_SESSION here; they are told apart by swapping its
 * content, as two browsers would have two different session files.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SessionStorageTest extends TestCase
{
    private string $dir;
    /** @var array<string, mixed> */
    private array $saved_session = [];

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/galette-debugbar-' . bin2hex(random_bytes(4));
        $this->saved_session = $_SESSION ?? [];
        $_SESSION = [];
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->markTestSkipped('A started session is required.');
        }
    }

    /**
     * Tear down
     */
    protected function tearDown(): void
    {
        $_SESSION = $this->saved_session;
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    /**
     * Save a request for current session
     *
     * @param SessionStorage $storage Storage
     * @param string         $id      Request id
     */
    private function saveRequest(SessionStorage $storage, string $id): void
    {
        $storage->save($id, ['__meta' => ['id' => $id, 'utime' => microtime(as_float: true), 'uri' => '/' . $id]]);
    }

    /**
     * Listing is paginated, most recent first, and filtered like FileStorage
     */
    public function testFind(): void
    {
        $storage = new SessionStorage(new FileStorage($this->dir), new Session());
        foreach (['a', 'b', 'c'] as $id) {
            $this->saveRequest($storage, $id);
        }

        $this->assertSame(['c', 'b', 'a'], array_column($storage->find(), 'id'));
        $this->assertSame(['b'], array_column($storage->find([], 1, 1), 'id'));
        $this->assertSame(['b'], array_column($storage->find(['uri' => '/b']), 'id'));
        $this->assertSame(['c', 'b'], array_column($storage->find(['uri' => '/[bc]']), 'id'));
    }

    /**
     * A session reads, lists and clears its own requests only
     */
    public function testSessionsAreIsolated(): void
    {
        $file_storage = new FileStorage($this->dir);
        $storage = new SessionStorage($file_storage, new Session());

        //first session
        $this->saveRequest($storage, 'first');
        $first_session = $_SESSION;

        //second session
        $_SESSION = [];
        $this->saveRequest($storage, 'second');
        $this->assertSame('second', $storage->get('second')['__meta']['id']);
        $this->assertSame(['second'], array_column($storage->find(), 'id'));

        try {
            $storage->get('first');
            $this->fail('A session must not read requests of another one');
        } catch (DebugBarException) {
            //expected
        }

        $storage->clear();
        $this->assertFileDoesNotExist($file_storage->makeFilename('second'));
        $this->assertFileExists($file_storage->makeFilename('first'));

        //back to first session: its data is still there
        $_SESSION = $first_session;
        $this->assertSame(['first'], array_column($storage->find(), 'id'));
        $this->assertSame('first', $storage->get('first')['__meta']['id']);
    }
}
