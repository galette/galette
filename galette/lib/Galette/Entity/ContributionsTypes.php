<?php

/**
 * Copyright © 2003-2024 The Galette Team
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
use Galette\Core\Db;
use ArrayObject;
use Galette\Features\I18n;
use Laminas\Db\Sql\Expression;
use Throwable;

/**
 * Contributions types handling
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $id
 * @property string $label
 * @property string $libelle
 * @property ?float $amount
 * @property int $extension
 */

class ContributionsTypes
{
    use I18n;

    public const DEFAULT_TYPE = -1;
    public const DONATION_TYPE = 0;

    public const TABLE = 'types_cotisation';
    public const PK = 'id_type_cotis';

    private Db $zdb;

    private int $id;
    private string $label;
    private ?float $amount;
    private int $extension;

    public const ID_NOT_EXITS = -1;

    /** @var array<string> */
    private array $errors = array();

    /** @var array<int, array<string, mixed>> */
    protected static array $defaults = array(
        array('id' => 1, 'libelle' => 'annual fee', 'extension' => self::DEFAULT_TYPE),
        array('id' => 2, 'libelle' => 'reduced annual fee', 'extension' => self::DEFAULT_TYPE),
        array('id' => 3, 'libelle' => 'company fee', 'extension' => self::DEFAULT_TYPE),
        array('id' => 4, 'libelle' => 'donation in kind', 'extension' => self::DONATION_TYPE),
        array('id' => 5, 'libelle' => 'donation in money', 'extension' => self::DONATION_TYPE),
        array('id' => 6, 'libelle' => 'partnership', 'extension' => self::DONATION_TYPE),
        array('id' => 7, 'libelle' => 'annual fee (to be paid)', 'extension' => self::DEFAULT_TYPE)
    );

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database
     * @param int|ArrayObject<string,int|string>|null $args Optional existing result set
     */
    public function __construct(Db $zdb, int|ArrayObject $args = null)
    {
        $this->zdb = $zdb;
        $this->extension = self::DEFAULT_TYPE;
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
                'Cannot load contribution type #' . $id . ' | ' .
                $e->getMessage(),
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
        $this->id = $r->{self::PK};
        $this->label = $r->libelle_type_cotis;
        $this->amount = (float)$r->amount;
        $this->extension = (int)$r->cotis_extension;
    }

    /**
     * Does current type give membership extension?
     *
     * @return boolean
     */
    public function isExtension(): bool
    {
        return $this->extension !== self::DONATION_TYPE;
    }

    /**
     * Get the amount
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
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
                'libelle_type_cotis' => ':libelle',
                'cotis_extension' => ':extension'
            ];

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            $this->zdb->handleSequence(
                self::TABLE,
                count(static::$defaults)
            );

            $fnames = array_values($values);
            foreach (self::$defaults as $d) {
                $stmt->execute(
                    array(
                        $fnames[0]  => $d['id'],
                        $fnames[1]  => $d['libelle'],
                        $fnames[2]  => $d['extension']
                    )
                );
            }

            Analog::log(
                'Defaults contributions types ' .
                ') were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize defaults contributions types' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get list in an array built as:
     * $array[id] = "translated label"
     *
     * @param boolean|null $extent Filter on (non) contributions types
     *
     * @return array<int, array<string, mixed>>
     */
    public function getList(bool $extent = null): array
    {
        $list = array();

        try {
            $select = $this->zdb->select(self::TABLE);
            $fields = array(self::PK, 'libelle_type_cotis', 'amount');
            $select->quantifier('DISTINCT');
            $select->columns($fields);
            $select->order(self::PK);

            if ($extent === true) {
                $select->where->notEqualTo('cotis_extension', self::DONATION_TYPE);
            } elseif ($extent === false) {
                $select->where->equalTo('cotis_extension', self::DONATION_TYPE);
            }

            $results = $this->zdb->execute($select);

            foreach ($results as $r) {
                $list[$r->{self::PK}] = [
                    'label' => _T($r->libelle_type_cotis),
                    'amount' => $r->amount
                ];
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
        $list = array();

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->order(array(self::PK));

            $results = $this->zdb->execute($select);

            if ($results->count() == 0) {
                Analog::log(
                    'No contributions types defined in database.',
                    Analog::INFO
                );
            } else {
                foreach ($results as $r) {
                    $list[$r->{self::PK}] = array(
                        'name'  => _T($r->libelle_type_cotis),
                        'amount' => $r->amount,
                        'extra' => $r->cotis_extension
                    );
                }
            }
            return $list;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list contributions types ' .
                ' | ' . $e->getMessage(),
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
        };
        return ($translated) ? _T($res->libelle_type_cotis) : $res->libelle_type_cotis;
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
            $select->columns(array(self::PK))
                ->where(array('libelle_type_cotis' => $label));

            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                return $result->{self::PK};
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to retrieve contribution type from label `' .
                $label . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Add a new entry
     *
     * @param string $label     The label
     * @param ?float $amount    The amount
     * @param int    $extension Membership extension in months, 0 for a donation or -1 for preferences default
     *
     * @return bool|integer  -2 : label already exists
     */
    public function add(string $label, ?float $amount, int $extension): bool|int
    {
        // Avoid duplicates.
        $label = strip_tags($label);
        $ret = $this->getIdByLabel($label);

        if ($ret !== false) {
            Analog::log(
                'A contribution type with label `' . $label . '` already exists',
                Analog::WARNING
            );
            return -2;
        }

        try {
            $this->zdb->connection->beginTransaction();
            $values = array(
                'libelle_type_cotis' => $label,
                'amount' => $amount ?? new Expression('NULL'),
                'cotis_extension' => $extension
            );

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);

            $ret = $this->zdb->execute($insert);

            if ($ret->count() > 0) {
                Analog::log(
                    'New contribution type `' . $label .
                    '` added successfully.',
                    Analog::INFO
                );

                $this->id = $this->zdb->getLastGeneratedValue($this);

                $this->addTranslation($label);
            } else {
                throw new \Exception('New contribution type not added.');
            }
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to add new contribution type `' . $label . '` | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Update in database.
     *
     * @param integer $id        Entry ID
     * @param string  $label     The label
     * @param ?float  $amount    The amount
     * @param int     $extension Membership extension in months, 0 for a donation or -1 for preferences default
     *
     * @return self::ID_NOT_EXITS|boolean
     */
    public function update(int $id, string $label, ?float $amount, int $extension): int|bool
    {
        $label = strip_tags($label);
        $ret = $this->get($id);
        if (!$ret) {
            /* get() already logged and set $this->error. */
            return self::ID_NOT_EXITS;
        }

        try {
            $oldlabel = $ret->libelle_type_cotis;
            $this->zdb->connection->beginTransaction();
            $values = array(
                'libelle_type_cotis' => $label,
                'amount' => $amount ?? new Expression('NULL'),
                'cotis_extension' => $extension
            );

            $update = $this->zdb->update(self::TABLE);
            $update->set($values);
            $update->where([self::PK => $id]);

            $this->zdb->execute($update);

            if ($oldlabel != $label) {
                $this->deleteTranslation($oldlabel);
                $this->addTranslation($label);
            }

            Analog::log(
                'Contribution type #' . $id . ' updated successfully.',
                Analog::INFO
            );
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to update contribution type #' . $id . ' | ' .
                $e->getMessage(),
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
            $this->deleteTranslation($ret->libelle_type_cotis);

            Analog::log(
                'Contribution type #' . $id . ' deleted successfully.',
                Analog::INFO
            );

            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to delete contribution type #' . $id .
                ' | ' . $e->getMessage(),
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
            $select = $this->zdb->select(Contribution::TABLE);
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
                'Unable to check if contribution type #' . $id .
                ' is used. | ' . $e->getMessage(),
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
        $forbidden = array();
        $virtuals = array('extension', 'libelle');
        if (
            in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
            switch ($name) {
                case 'libelle':
                    return _T($this->label);
                default:
                    return $this->$name;
            }
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
        $forbidden = array();
        $virtuals = array('extension', 'libelle');
        if (
            in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
            return true;
        }

        return false;
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
