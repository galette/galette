<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Entity;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Core\Db;
use ArrayObject;
use Galette\Core\Login;
use Galette\Features\I18n;
use Galette\Repository\Members;
use Throwable;

/**
 * Members status
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Status
{
    use I18n;

    public const DEFAULT_STATUS = 9;
    public const TABLE = 'statuts';
    public const PK = 'id_statut';

    public const ID_NOT_EXITS = -1;

    private int $id;
    private string $label;
    private int $priority;
    #[Inject]
    private Login $login;

    public const ID_NOT_EXISTS = -1;

    /** @var array<string> */
    private array $errors = [];

    /** @var array<int, array<string, mixed>> */
    protected static array $defaults = [
        ['id' => 1, 'libelle' => 'President', 'priority' => 0],
        ['id' => 2, 'libelle' => 'Treasurer', 'priority' => 10],
        ['id' => 3, 'libelle' => 'Secretary', 'priority' => 20],
        ['id' => 4, 'libelle' => 'Active member', 'priority' => 30],
        ['id' => 5, 'libelle' => 'Benefactor member', 'priority' => 40],
        ['id' => 6, 'libelle' => 'Founder member', 'priority' => 50],
        ['id' => 7, 'libelle' => 'Old-timer', 'priority' => 60],
        ['id' => 8, 'libelle' => 'Society', 'priority' => 70],
        ['id' => 9, 'libelle' => 'Non-member', 'priority' => 80],
        ['id' => 10, 'libelle' => 'Vice-president', 'priority' => 5]
    ];

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database
     * @param int|ArrayObject<string,int|string>|null $args Optional existing result set
     */
    public function __construct(private Db $zdb, int|ArrayObject|null $args = null)
    {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads an entry from its id
     *
     * @param int $id Entry ID
     *
     * @return boolean true if query succeed, false otherwise
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                /** @var ArrayObject<string, int|string> $result */
                $result = $results->current();
                $this->loadFromRS($result);

                return true;
            } else {
                Analog::log(
                    'Unknown ID ' . $id,
                    Analog::ERROR
                );
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load status #' . $id . ' | '
                . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->{self::PK};
        $this->label = $r->libelle_statut;
        $this->priority = (int)$r->priorite_statut;
    }

    /**
     * Set defaults at install time
     *
     * @return boolean
     * @throws Throwable
     */
    public function installInit(): bool
    {
        try {
            //first, we drop all values
            $delete = $this->zdb->delete(self::TABLE);
            $this->zdb->execute($delete);

            $values = [
                self::PK => ':id',
                'libelle_statut' => ':libelle',
                'priorite_statut' => ':extension'
            ];

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            $this->zdb->handleSequence(
                self::TABLE,
                self::PK,
                count(static::$defaults)
            );

            $fnames = array_values($values);
            foreach (self::$defaults as $d) {
                $stmt->execute(
                    [
                        $fnames[0]  => $d['id'],
                        $fnames[1]  => $d['libelle'],
                        $fnames[2]  => $d['priority']
                    ]
                );
            }

            Analog::log(
                'Defaults status '
                . ') were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize defaults status '
                . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get list in an array built as:
     * $array[id] = "translated label"
     *
     * @return array<int, string>
     */
    public function getList(): array
    {
        $list = [];

        try {
            $select = $this->zdb->select(self::TABLE);
            $fields = [self::PK, 'libelle_statut', 'priorite_statut'];
            $select->quantifier('DISTINCT');
            $select->columns($fields);
            $select->order('priorite_statut');

            $results = $this->zdb->execute($select);

            foreach ($results as $r) {
                if (
                    !$this->login->isStaff()
                    && !$this->login->isAdmin()
                    && $r->priorite_statut < Members::NON_STAFF_MEMBERS
                ) {
                    continue;
                }
                $list[$r->{self::PK}] = _T($r->libelle_statut);
            }
            return $list;
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Complete list
     *
     * @return array<int, array<string,mixed>> of all objects
     */
    public function getCompleteList(): array
    {
        $list = [];

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->order(['priorite_statut', self::PK]);

            $results = $this->zdb->execute($select);

            if ($results->count() == 0) {
                Analog::log(
                    'No status defined in database.',
                    Analog::INFO
                );
            } else {
                foreach ($results as $r) {
                    $list[$r->{self::PK}] = [
                        'text_orig' => $r->libelle_statut,
                        'name' => _T($r->libelle_statut),
                        'extra' => $r->priorite_statut
                    ];
                }
            }
            return $list;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list status '
                . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get an entry
     *
     * @param integer $id Entry ID
     *
     * @return ArrayObject<string, int|string>|false Row if succeed ; false: no such id
     */
    public function get(int $id): ArrayObject|false
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $result = $results->current();

            if (!$result) {
                $this->errors[] = _T("Label does not exist");
                return false;
            }

            return $result;
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get a label
     *
     * @param integer $id         Id
     * @param boolean $translated Do we want translated or original label?
     *                            Defaults to true.
     *
     * @return string|int
     */
    public function getLabel(int $id, bool $translated = true): string|int
    {
        $res = $this->get($id);
        if ($res === false) {
            //get() already logged
            return self::ID_NOT_EXITS;
        }
        return ($translated) ? _T($res->libelle_statut) : $res->libelle_statut;
    }

    /**
     * Get an ID from a label
     *
     * @param string $label The label
     *
     * @return int|false Return id if it exists false otherwise
     */
    public function getIdByLabel(string $label): int|false
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns([self::PK])
                ->where(['libelle_statut' => $label]);

            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                return (int)$result->{self::PK};
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to retrieve status from label `'
                . $label . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Add a new entry
     *
     * @param string  $label The label
     * @param integer $extra Extra values (priority for statuses,
     *                       extension for contributions types, ...)
     *
     * @return bool|integer  -2 : label already exists
     */
    public function add(string $label, int $extra): bool|int
    {
        // Avoid duplicates.
        $label = strip_tags($label);
        $ret = $this->getIdByLabel($label);

        if ($ret !== false) {
            Analog::log(
                'A status with label `' . $label . '` already exists',
                Analog::WARNING
            );
            return -2;
        }

        try {
            $this->zdb->connection->beginTransaction();
            $values = [
                'libelle_statut'  => $label,
                'priorite_statut' => $extra
            ];

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);

            $ret = $this->zdb->execute($insert);

            if ($ret->count() > 0) {
                Analog::log(
                    'New status `' . $label
                    . '` added successfully.',
                    Analog::INFO
                );

                $this->id = $this->zdb->getLastGeneratedValue($this);

                $this->addTranslation($label);
            } else {
                throw new \Exception('New status not added.');
            }
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to add new status `' . $label . '` | '
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Update in database.
     *
     * @param integer $id    Entry ID
     * @param string  $label The label
     * @param integer $extra Extra values (priority for statuses,
     *                       extension for contributions types, ...)
     *
     * @return self::ID_NOT_EXITS|boolean
     */
    public function update(int $id, string $label, int $extra): int|bool
    {
        $label = strip_tags($label);
        $ret = $this->get($id);
        if (!$ret) {
            /* get() already logged and set $this->error. */
            return self::ID_NOT_EXITS;
        }

        try {
            $oldlabel = $ret->libelle_statut;
            $this->zdb->connection->beginTransaction();
            $values = [
                'libelle_statut' => $label,
                'priorite_statut' => $extra
            ];

            $update = $this->zdb->update(self::TABLE);
            $update->set($values);
            $update->where([self::PK => $id]);

            $this->zdb->execute($update);

            if ($oldlabel != $label) {
                $this->deleteTranslation($oldlabel);
                $this->addTranslation($label);
            }

            Analog::log(
                'Status #' . $id . ' updated successfully.',
                Analog::INFO
            );
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to update status #' . $id . ' | '
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Delete entry
     *
     * @param integer $id Entry ID
     *
     * @return self::ID_NOT_EXITS|boolean
     */
    public function delete(int $id): int|bool
    {
        if ($id === self::DEFAULT_STATUS) {
            throw new \RuntimeException(_T("You cannot delete default status!"));
        }

        $ret = $this->get($id);
        if (!$ret) {
            /* get() already logged */
            return self::ID_NOT_EXITS;
        }

        if ($this->isUsed($id)) {
            $this->errors[] = _T("Cannot delete this label: it's still used");
            return false;
        }

        try {
            $this->zdb->connection->beginTransaction();
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);

            $this->zdb->execute($delete);
            $this->deleteTranslation($ret->libelle_statut);

            Analog::log(
                'Statut #' . $id . ' deleted successfully.',
                Analog::INFO
            );

            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to delete status  #' . $id
                . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Check if this entry is used.
     *
     * @param integer $id Entry ID
     *
     * @return boolean
     */
    public function isUsed(int $id): bool
    {
        try {
            $select = $this->zdb->select(Adherent::TABLE);
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $result = $results->current();

            if ($result !== null) {
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to check if status #' . $id
                . ' is used. | ' . $e->getMessage(),
                Analog::ERROR
            );
            //in case of error, we consider that it is used, to avoid errors
            return true;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        $forbidden = [];
        $virtuals = ['extension', 'libelle'];
        if (in_array($name, $virtuals)
        || !in_array($name, $forbidden)
        && isset($this->$name)) {
            return match ($name) {
                'libelle' => _T($this->label),
                default => $this->$name,
            };
        } else {
            return false;
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $forbidden = [];
        $virtuals = ['extension', 'libelle'];
        return in_array($name, $virtuals) || !in_array($name, $forbidden) && isset($this->$name);
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
