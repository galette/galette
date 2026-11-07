<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug;

use DebugBar\DebugBarException;
use DebugBar\Storage\StorageInterface;
use RKA\Session;

use function Safe\unlink;

/**
 * Debug bar storage restricted to current session
 *
 * Collected data holds POST values, SQL and emails; a session can only read,
 * list and clear what it produced itself.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SessionStorage implements StorageInterface
{
    public const string SESSION_KEY = 'debugbar_ids';
    private const int MAX_IDS = 100;

    /**
     * @param StorageInterface $storage Real storage
     * @param Session          $session Current session
     */
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Session $session
    ) {
    }

    /**
     * Save data, and remember it belongs to current session
     *
     * @param string               $id   Request id
     * @param array<string, mixed> $data Collected data
     */
    public function save(string $id, array $data): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $ids = $this->getIds();
        $ids[] = $id;
        $this->session->set(self::SESSION_KEY, array_slice(array_values(array_unique($ids)), -self::MAX_IDS));

        $this->storage->save($id, $data);
    }

    /**
     * Get data of a request of current session
     *
     * @param string $id Request id
     *
     * @return array<string, mixed>
     *
     * @throws DebugBarException
     */
    public function get(string $id): array
    {
        if (!in_array($id, $this->getIds(), strict: true)) {
            throw new DebugBarException('Unknown request');
        }
        return $this->storage->get($id);
    }

    /**
     * Find requests of current session
     *
     * @param array<string, mixed> $filters Filters
     * @param int                  $max     Maximum number of results
     * @param int                  $offset  Offset
     *
     * @return array<int, mixed>
     */
    public function find(array $filters = [], int $max = 20, int $offset = 0): array
    {
        $min_utime = isset($filters['utime']) ? (float)$filters['utime'] : null;
        unset($filters['utime']);

        //only read requests of this session (bounded), most recent first
        $results = [];
        foreach (array_reverse($this->getIds()) as $id) {
            $meta = $this->storage->get($id)['__meta'] ?? null;
            if (!is_array($meta)) {
                continue;
            }
            if ($min_utime !== null && ($meta['utime'] ?? 0) <= $min_utime) {
                break;
            }
            if (!$this->matches($meta, $filters)) {
                continue;
            }
            $results[] = $meta;
            if (count($results) >= $max + $offset) {
                break;
            }
        }

        return array_slice($results, $offset, $max);
    }

    /**
     * Does a request match filters? Same rules as FileStorage
     *
     * @param array<string, mixed> $meta    Request metadata
     * @param array<string, mixed> $filters Filters
     */
    private function matches(array $meta, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if (!isset($meta[$key]) || !fnmatch((string)$value, (string)$meta[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Clear requests of current session only
     */
    public function clear(): void
    {
        $ids = $this->getIds();
        if ($this->storage instanceof \DebugBar\Storage\FileStorage) {
            foreach ($ids as $id) {
                $file = $this->storage->makeFilename($id);
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $this->session->delete(self::SESSION_KEY);
    }

    /**
     * Prune old data
     *
     * @param int $hours Data older than this number of hours is removed
     */
    public function prune(int $hours = 24): void
    {
        $this->storage->prune($hours);
    }

    /**
     * Ids of requests made by current session
     *
     * @return list<string>
     */
    private function getIds(): array
    {
        $ids = $this->session->get(self::SESSION_KEY, []);
        return is_array($ids) ? array_values(array_filter($ids, is_string(...))) : [];
    }
}
