<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use ArrayObject;
use Closure;

/**
 * Rows of small reference tables (statuses, titles, ...), read once per request
 *
 * Lists show the label of a reference row for each of their entries; reading the
 * whole table once avoids a query per entry. Writes run from Db::execute() forget
 * the table, a rollback forgets everything.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class RowsCache
{
    /** @var array<string, array<int, ArrayObject<string, mixed>>> */
    private static array $rows = [];
    /** @var array<string, array<string, list<ArrayObject<string, mixed>>>> */
    private static array $queries = [];
    /** @var array<string, array<string, array<mixed>>> */
    private static array $values = [];

    /**
     * Get a row
     *
     * @param Db     $zdb   Database instance
     * @param string $table Table name, without prefix
     * @param string $pk    Primary key column
     * @param int    $id    Primary key value
     *
     * @return ?ArrayObject<string, mixed> A copy of the row, null when it does not exist
     */
    public static function get(Db $zdb, string $table, string $pk, int $id): ?ArrayObject
    {
        $row = self::load($zdb, $table, $pk)[$id] ?? null;
        return $row !== null ? clone $row : null;
    }

    /**
     * Get all rows
     *
     * @param Db     $zdb   Database instance
     * @param string $table Table name, without prefix
     * @param string $pk    Primary key column
     *
     * @return array<int, ArrayObject<string, mixed>> Copies of the rows, by primary key
     */
    public static function all(Db $zdb, string $table, string $pk): array
    {
        return array_map(
            fn(ArrayObject $row): ArrayObject => clone $row,
            self::load($zdb, $table, $pk)
        );
    }

    /**
     * Get rows of a query on a table, run once
     *
     * @param string                                          $table Table name, without prefix; writes to it forget the rows
     * @param string                                          $key   Query identifier, unique for the table
     * @param Closure(): iterable<ArrayObject<string, mixed>> $query Runs the query
     *
     * @return list<ArrayObject<string, mixed>> Copies of the rows, in query order
     */
    public static function query(string $table, string $key, Closure $query): array
    {
        $prefixed = PREFIX_DB . $table;
        if (!isset(self::$queries[$prefixed][$key])) {
            $rows = [];
            foreach ($query() as $row) {
                $rows[] = $row;
            }
            self::$queries[$prefixed][$key] = $rows;
        }

        return array_map(
            fn(ArrayObject $row): ArrayObject => clone $row,
            self::$queries[$prefixed][$key]
        );
    }

    /**
     * Get a value computed from a table, once
     *
     * @param string                  $table   Table name, without prefix; writes to it forget the value
     * @param string                  $key     Value identifier, unique for the table
     * @param Closure(): array<mixed> $compute Computes the value
     *
     * @return array<mixed>
     */
    public static function remember(string $table, string $key, Closure $compute): array
    {
        return self::$values[PREFIX_DB . $table][$key] ??= $compute();
    }

    /**
     * Load rows of a table, once
     *
     * @param Db     $zdb   Database instance
     * @param string $table Table name, without prefix
     * @param string $pk    Primary key column
     *
     * @return array<int, ArrayObject<string, mixed>>
     */
    private static function load(Db $zdb, string $table, string $pk): array
    {
        $key = PREFIX_DB . $table;
        if (!isset(self::$rows[$key])) {
            $rows = [];
            /** @var ArrayObject<string, mixed> $row */
            foreach ($zdb->execute($zdb->select($table)->order($pk)) as $row) {
                $rows[(int)$row->$pk] = $row;
            }
            self::$rows[$key] = $rows;
        }
        return self::$rows[$key];
    }

    /**
     * Forget rows of a table
     *
     * @param string $table Table name, with prefix
     */
    public static function forget(string $table): void
    {
        unset(self::$rows[$table], self::$queries[$table], self::$values[$table]);
    }

    /**
     * Forget all rows
     */
    public static function clear(): void
    {
        self::$rows = [];
        self::$queries = [];
        self::$values = [];
    }
}
