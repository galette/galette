<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Preferences;

use Analog\Analog;
use Galette\Core\Db;
use Throwable;

/**
 * The preferences table
 *
 * Every statement run against galette_preferences lives here, so that reading
 * a preference, deciding what to write and knowing how it is stored stop being
 * the same piece of code.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Storage
{
    public const string TABLE = 'preferences';
    public const string PK = 'id_pref';

    /** Rows that predate the autoincrement column */
    private const int LEGACY_ROWS = 7;

    /**
     * Constructor
     *
     * @param Db $zdb Db instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Read every stored preference
     *
     * @return array<string, string>|null Null when the table cannot be read
     */
    public function readAll(): ?array
    {
        try {
            $values = [];
            foreach ($this->zdb->selectAll(self::TABLE) as $pref) {
                $values[$pref->nom_pref] = $pref->val_pref;
            }

            return $values;
        } catch (Throwable) {
            Analog::log(
                'Preferences cannot be loaded. Galette should not work without '
                . 'preferences. Exiting.',
                Analog::URGENT
            );
            return null;
        }
    }

    /**
     * Add preferences the table does not hold yet
     *
     * @param array<string, mixed> $values Values to insert
     */
    public function insertMissing(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        try {
            $this->zdb->handleSequence(self::TABLE, self::PK, self::LEGACY_ROWS);
            $this->insertAll($values);
        } catch (Throwable $e) {
            Analog::log(
                sprintf('Unable to add missing preferences. %s', $e->getMessage()),
                Analog::WARNING
            );
            return false;
        }

        Analog::log(
            'Missing preferences were successfully stored into database.',
            Analog::INFO
        );

        return true;
    }

    /**
     * Drop every preference and store the given ones
     *
     * @param array<string, mixed> $values Values to store
     *
     * @throws Throwable
     */
    public function replaceAll(array $values): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $this->zdb->execute($delete);

            $this->insertAll($values);
            $this->zdb->handleSequence(self::TABLE, self::PK, count($values));

            Analog::log(
                'Default preferences were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default preferences.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Write one preference
     *
     * @param string $name  Preference name
     * @param mixed  $value Value to store
     */
    public function updateOne(string $name, mixed $value): bool
    {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update
                ->set(['val_pref' => $value])
                ->where->equalTo('nom_pref', $name);
            $this->zdb->execute($update);

            Analog::log(sprintf('%s updated.', $name), Analog::INFO);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                sprintf('Unable to store update field %s | %s', $name, print_r($this->messages($e), true)),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Write several preferences at once
     *
     * @param array<string, mixed> $values Values to store
     */
    public function updateMany(array $values): bool
    {
        try {
            $this->zdb->beginTransaction();

            $update = $this->zdb->update(self::TABLE);
            $update->set(['val_pref' => ':val_pref'])->where->equalTo('nom_pref', ':nom_pref');
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            foreach ($values as $name => $value) {
                Analog::log('Storing ' . $name, Analog::DEBUG);
                $stmt->execute(['val_pref' => $value, 'nom_pref' => $name]);
            }

            $this->zdb->commit();
            Analog::log(
                'Preferences were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
            }

            Analog::log(
                'Unable to store preferences | ' . print_r($this->messages($e), true),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Insert a batch of preferences
     *
     * @param array<string, mixed> $values Values to insert
     *
     * @throws Throwable
     */
    private function insertAll(array $values): void
    {
        $insert = $this->zdb->insert(self::TABLE);
        $insert->values(['nom_pref' => ':nom_pref', 'val_pref' => ':val_pref']);
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

        foreach ($values as $name => $value) {
            $stmt->execute(['nom_pref' => $name, 'val_pref' => $value]);
        }
    }

    /**
     * Flatten an exception chain into readable messages
     *
     * @param Throwable $e Exception to walk
     *
     * @return array<string>
     */
    private function messages(Throwable $e): array
    {
        $messages = [];
        do {
            $messages[] = $e->getMessage();
        } while ($e = $e->getPrevious());

        return $messages;
    }
}
