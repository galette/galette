<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Safe\DateTime;
use Exception;
use Galette\Core\Db;
use Galette\Core\Galette;
use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Adapter;

/**
 * Import model entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ImportModel
{
    public const string TABLE = 'import_model';
    public const string PK = 'model_id';

    private ?int $id = null;
    /** @var array<string>|null */
    private ?array $fields = null;
    private ?string $creation_date = null;

    /**
     * Loads model
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(): bool
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);
            $select->limit(1);

            $results = $zdb->execute($select);
            $result = $results->current();

            if ($result) {
                $this->loadFromRS($result);
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load import model | ' . $e->getMessage()
                . "\n" . $e->__toString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->model_id;
        if (Galette::isSerialized($r->model_fields)) {
            $this->fields = unserialize($r->model_fields);
        } else {
            $this->fields = Galette::jsonDecode($r->model_fields);
        }
        $this->creation_date = $r->model_creation_date;
    }

    /**
     * Remove model
     *
     * @param Db $zdb Database instance
     */
    public function remove(Db $zdb): bool
    {
        try {
            $zdb->db->query(
                'DELETE FROM ' . PREFIX_DB . self::TABLE,
                Adapter::QUERY_MODE_EXECUTE
            );

            $this->id = null;
            $this->fields = null;
            $this->creation_date = null;
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to remove import model ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store the model
     *
     * @param Db $zdb Database instance
     */
    public function store(Db $zdb): bool
    {
        try {
            $values = [
                'model_fields'  => Galette::jsonEncode($this->fields)
            ];

            if (!isset($this->id)) {
                //we're inserting a new model
                $this->creation_date = date("Y-m-d H:i:s");
                $values['model_creation_date'] = $this->creation_date;

                $insert = $zdb->insert(self::TABLE);
                $insert->values($values);
                $results = $zdb->execute($insert);

                if ($results->count() > 0) {
                    return true;
                } else {
                    throw new Exception(
                        'An error occurred inserting new import model!'
                    );
                }
            } else {
                //we're editing an existing model
                $values[self::PK] = $this->id;
                $update = $zdb->update(self::TABLE);
                $update->set($values);
                $update->where([self::PK => $this->id]);
                $zdb->execute($update);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong storing import model :\'( | '
                . $e->getMessage() . "\n" . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get fields
     *
     * @return ?array<string>
     */
    public function getFields(): ?array
    {
        return $this->fields ?? null;
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getCreationDate(bool $formatted = true): string
    {
        if ($formatted === true) {
            $date = new DateTime($this->creation_date);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->creation_date;
        }
    }

    /**
     * Set fields
     *
     * @param array<string> $fields Fields list
     */
    public function setFields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }
}
