<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use Galette\Util\Html;
use Analog\Analog;
use Galette\Core\Db;
use ArrayObject;
use Galette\Features\I18n;
use Laminas\Db\Sql\Expression;
use Throwable;

use function Safe\preg_replace;

/**
 * Contributions types handling
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property int    $id
 * @property string $label
 * @property string $description
 * @property string $libelle
 * @property ?float $amount
 * @property int    $extension
 */

class ContributionsTypes
{
    use I18n;

    public const int DEFAULT_TYPE = -1;
    public const int DONATION_TYPE = 0;

    public const string TABLE = 'types_cotisation';
    public const string PK = 'id_type_cotis';

    private int $id;
    private string $label;
    private string $description;
    private ?float $amount = null;
    private int $extension;

    public const int ID_NOT_EXITS = -1;

    /** @var array<string> */
    private array $errors = [];

    /** @var array<int, array<string, mixed>> */
    protected static array $defaults = [
        ['id' => 1, 'libelle' => 'annual fee', 'description' => '', 'extension' => self::DEFAULT_TYPE],
        ['id' => 2, 'libelle' => 'reduced annual fee', 'description' => '', 'extension' => self::DEFAULT_TYPE],
        ['id' => 3, 'libelle' => 'company fee', 'description' => '', 'extension' => self::DEFAULT_TYPE],
        ['id' => 4, 'libelle' => 'donation in kind', 'description' => '', 'extension' => self::DONATION_TYPE],
        ['id' => 5, 'libelle' => 'donation in money', 'description' => '', 'extension' => self::DONATION_TYPE],
        ['id' => 6, 'libelle' => 'partnership', 'description' => '', 'extension' => self::DONATION_TYPE],
        ['id' => 7, 'libelle' => 'annual fee (to be paid)', 'description' => '', 'extension' => self::DEFAULT_TYPE]
    ];

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database
     * @param int|ArrayObject<string,int|string>|null $args Optional existing result set
     */
    public function __construct(
        private Db $zdb,
        int|ArrayObject|null $args = null
    ) {
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
     * @return bool true if query succeed, false otherwise
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
                'Cannot load contribution type #' . $id . ' | '
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
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->{self::PK};
        $this->label = $r->libelle_type_cotis;
        $this->description = $r->description;
        if ($r->amount !== null) {
            $this->amount = (float)$r->amount;
        }
        $this->extension = (int)$r->cotis_extension;
    }

    /**
     * Does current type give membership extension?
     */
    public function isExtension(): bool
    {
        return $this->extension !== self::DONATION_TYPE;
    }

    /**
     * Get the amount
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Set defaults at install time
     *
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
                'description' => ':description',
                'cotis_extension' => ':extension'
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
                        $fnames[2]  => $d['description'],
                        $fnames[3]  => $d['extension']
                    ]
                );
            }

            Analog::log(
                'Defaults contributions types '
                . ') were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize defaults contributions types'
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
     * @param bool|null $extent Filter on (non) contributions types
     *
     * @return array<int, array<string, mixed>>
     */
    public function getList(?bool $extent = null): array
    {
        $list = [];

        try {
            $select = $this->zdb->select(self::TABLE);
            $fields = [self::PK, 'libelle_type_cotis', 'description', 'amount', 'cotis_extension'];
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
                    'description' => $r->description,
                    'amount' => $r->amount,
                    'extension' => $r->cotis_extension
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
        $list = [];

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->order([self::PK]);

            $results = $this->zdb->execute($select);

            if ($results->count() == 0) {
                Analog::log(
                    'No contributions types defined in database.',
                    Analog::INFO
                );
            } else {
                foreach ($results as $r) {
                    $list[$r->{self::PK}] = [
                        'text_orig' => $r->libelle_type_cotis,
                        'name' => _T($r->libelle_type_cotis),
                        'description' => $r->description,
                        'amount' => $r->amount,
                        'extra' => $r->cotis_extension
                    ];
                }
            }
            return $list;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list contributions types '
                . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get an entry
     *
     * @param int $id Entry ID
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
     * @param int  $id         Id
     * @param bool $translated Do we want translated or original label?
     *                         Defaults to true.
     */
    public function getLabel(int $id, bool $translated = true): string|int
    {
        $res = $this->get($id);
        if ($res === false) {
            //get() already logged
            return self::ID_NOT_EXITS;
        }
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
            $select->columns([self::PK])
                ->where(['libelle_type_cotis' => $label]);

            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                return (int)$result->{self::PK};
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to retrieve contribution type from label `'
                . $label . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Add a new entry
     *
     * @param string $label       The label
     * @param string $description The description
     * @param ?float $amount      The amount
     * @param int    $extension   Membership extension in months, 0 for a donation or -1 for preferences default
     *
     * @return bool|int  -2 : label already exists
     */
    public function add(string $label, string $description, ?float $amount, int $extension): bool|int
    {
        // Avoid duplicates.
        $label = strip_tags($label);
        $description = $this->formatDescription($description);
        $ret = $this->getIdByLabel($label);

        if ($ret !== false) {
            Analog::log(
                'A contribution type with label `' . $label . '` already exists',
                Analog::WARNING
            );
            return -2;
        }

        try {
            $this->zdb->beginTransaction();
            $values = [
                'libelle_type_cotis' => $label,
                'description' => $description,
                'amount' => $amount ?? new Expression('NULL'),
                'cotis_extension' => $extension
            ];

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);

            $ret = $this->zdb->execute($insert);

            if ($ret->count() > 0) {
                Analog::log(
                    'New contribution type `' . $label
                    . '` added successfully.',
                    Analog::INFO
                );

                $this->id = $this->zdb->getLastGeneratedValue($this);

                $this->addTranslation($label);
            } else {
                throw new \Exception('New contribution type not added.');
            }
            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Analog::log(
                'Unable to add new contribution type `' . $label . '` | '
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Update in database.
     *
     * @param int    $id          Entry ID
     * @param string $label       The label
     * @param string $description The description
     * @param ?float $amount      The amount
     * @param int    $extension   Membership extension in months, 0 for a donation or -1 for preferences default
     *
     * @return self::ID_NOT_EXITS|bool
     */
    public function update(int $id, string $label, string $description, ?float $amount, int $extension): int|bool
    {
        $label = strip_tags($label);
        $description = $this->formatDescription($description);
        $ret = $this->get($id);
        if (!$ret) {
            /* get() already logged and set $this->error. */
            return self::ID_NOT_EXITS;
        }

        try {
            $oldlabel = $ret->libelle_type_cotis;
            $this->zdb->beginTransaction();
            $values = [
                'libelle_type_cotis' => $label,
                'description' => $description,
                'amount' => $amount ?? new Expression('NULL'),
                'cotis_extension' => $extension
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
                'Contribution type #' . $id . ' updated successfully.',
                Analog::INFO
            );
            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Analog::log(
                'Unable to update contribution type #' . $id . ' | '
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Delete entry
     *
     * @param int $id Entry ID
     *
     * @return self::ID_NOT_EXITS|bool
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
            $this->zdb->beginTransaction();
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);

            $this->zdb->execute($delete);
            $this->deleteTranslation($ret->libelle_type_cotis);

            Analog::log(
                'Contribution type #' . $id . ' deleted successfully.',
                Analog::INFO
            );

            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Analog::log(
                'Unable to delete contribution type #' . $id
                . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Check if this entry is used.
     *
     * @param int $id Entry ID
     */
    public function isUsed(int $id): bool
    {
        try {
            $select = $this->zdb->select(Contribution::TABLE);
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $result = $results->current();

            return $result !== null;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to check if contribution type #' . $id
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
        if (
            in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
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

    /**
     * Format description to remove empty tags
     */
    private function formatDescription(string $description): string
    {
        //If we just have empty tags, we consider that description is empty
        if (trim(strip_tags($description)) === '') {
            return '';
        }

        // Remove leading and trailing empty paragraphs (<p><br></p>) added by WYSIWYG editors,
        // but preserve intentional <br> tags inside non-empty content.
        $cleaned = preg_replace(
            [
                '/^(?:\s*<br\s*\/?>\s*)+/i',
                '/(?:\s*<br\s*\/?>\s*)+$/i',
                '/^(?:\s*<p>\s*<br\s*\/?>\s*<\/p>\s*)+/i',
                '/(?:\s*<p>\s*<br\s*\/?>\s*<\/p>\s*)+$/i',
                '/<p>\s*<br\s*\/?>\s*<\/p>/i'
            ],
            '',
            $description
        );
        $cleaned = Html::clean((string)$cleaned);
        return trim((string)$cleaned);
    }
}
