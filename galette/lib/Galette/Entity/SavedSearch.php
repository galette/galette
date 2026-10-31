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
use Galette\Core\Galette;
use RuntimeException;
use Throwable;
use Galette\Core\Db;
use Galette\Core\Login;
use Analog\Analog;
use Galette\Core\Logs;
use Galette\Util\RelativeDate;

use function Safe\json_encode;

/**
 * Saved search
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property      int                  $id
 * @property      string               $name
 * @property      array<string, mixed> $parameters
 * @property      int                  $author_id
 * @property      string               $creation_date
 * @property      string               $form
 * @property-read array<string, mixed> $sparameters
 */

class SavedSearch
{
    public const string TABLE = 'searches';
    public const string PK = 'search_id';
    /** @var array<string> Pagination is not a search criteria */
    private const array IGNORED_PARAMETERS = ['nbshow', 'page'];

    private int $id;
    private string $name;
    /** @var array<string, mixed> */
    private array $parameters = [];
    private ?int $author_id = null;
    private ?string $creation_date;
    private string $form;

    /** @var array<string> */
    private array $errors = [];

    /**
     * Main constructor
     *
     * @param Db                                      $zdb   Database instance
     * @param Login                                   $login Login instance
     * @param ArrayObject<string,int|string>|int|null $args  Arguments
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login,
        ArrayObject|int|null $args = null
    ) {
        $this->creation_date = date('Y-m-d H:i:s');

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load a saved search from its identifier
     *
     * @param int $id Identifier
     */
    private function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);
            if ($this->login->isSuperAdmin()) {
                $select->where(Adherent::PK . ' IS NULL');
            } else {
                $select->where([Adherent::PK => $this->login->id]);
            }

            $results = $this->zdb->execute($select);
            /** @var ?ArrayObject<string, int|string> $res */
            $res = $results->current();
        } catch (Throwable $e) {
            Logs::exception($e, 'An error occurred loading saved search #' . $id);
            throw $e;
        }

        if ($res === null) {
            throw new RuntimeException(
                'Saved search #' . $id . ' does not exist or is not owned by current user'
            );
        }
        $this->loadFromRS($res);
    }

    /**
     * Load a saved search from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        $pk = self::PK;
        $this->id = (int)$rs->$pk;
        $this->name = $rs->name ?? '';
        try {
            $this->parameters = $this->filterParameters(Galette::jsonDecode($rs->parameters));
        } catch (RuntimeException $e) {
            Logs::exception($e, 'Unable to decode parameters for saved search #' . $this->id);
            $this->parameters = [];
        }
        if ($rs->id_adh !== null) {
            $this->author_id = (int)$rs->id_adh;
        }
        $this->creation_date = $rs->creation_date;
        $this->form = $rs->form;
    }

    /**
     * Remove parameters that are not search criteria
     *
     * @param array<string, mixed> $parameters Parameters
     *
     * @return array<string, mixed>
     */
    private function filterParameters(array $parameters): array
    {
        return array_diff_key($parameters, array_flip(self::IGNORED_PARAMETERS));
    }

    /**
     * Check and set values
     *
     * @param array<string, mixed> $values Values to set
     */
    public function check(array $values): bool
    {
        $this->errors = [];
        $mandatory = [
            'form'  => _T('Form is mandatory!')
        ];

        foreach ($values as $key => $value) {
            if ($key === 'parameters' && is_array($value)) {
                $value = $this->filterParameters($value);
            }
            if (empty($value) && isset($mandatory[$key])) {
                $this->errors[] = $mandatory[$key];
            }
            $this->$key = $value;
            unset($mandatory[$key]);
        }

        if (count($mandatory)) {
            $this->errors = array_merge($this->errors, $mandatory);
        }

        if (!isset($this->id) && !$this->login->isSuperAdmin()) {
            //set author for new searches
            $this->author_id = $this->login->id;
        }

        return count($this->errors) === 0;
    }

    /**
     * Store saved search in database
     */
    public function store(): bool
    {
        $parameters = json_encode($this->parameters);

        if (isset($this->id)) {
            return $this->update($parameters);
        }

        $data = [
            'name'              => $this->name,
            'parameters'        => $parameters,
            'id_adh'            => $this->author_id,
            'creation_date'     => $this->creation_date ?? date('Y-m-d H:i:s'),
            'form'              => $this->form
        ];

        try {
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($data);
            $add = $this->zdb->execute($insert);
            if (!$add->count() > 0) {
                Analog::log('Not stored!', Analog::ERROR);
                return false;
            }
            $this->id = $this->zdb->getLastGeneratedValue($this);
            return true;
        } catch (Throwable $e) {
            Logs::exception($e, "An error occurred storing saved search:\n" . print_r($data, return: true));
            throw $e;
        }
    }

    /**
     * Update saved search in database
     *
     * Only name and parameters can change; author and creation date are kept.
     *
     * @param string $parameters JSON encoded parameters
     */
    private function update(string $parameters): bool
    {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'name'          => $this->name,
                    'parameters'    => $parameters
                ]
            );
            $update->where([self::PK => $this->id]);
            if ($this->login->isSuperAdmin()) {
                $update->where(Adherent::PK . ' IS NULL');
            } else {
                $update->where([Adherent::PK => $this->login->id]);
            }
            //no affected rows count check: MySQL reports 0 when nothing changed
            $this->zdb->execute($update);
            return true;
        } catch (Throwable $e) {
            Logs::exception($e, 'An error occurred updating saved search #' . $this->id);
            throw $e;
        }
    }

    /**
     * Remove current saved search
     */
    public function remove(): bool
    {
        $id = $this->id;
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            if ($this->login->isSuperAdmin()) {
                $delete->where(Adherent::PK . ' IS NULL');
            } else {
                $delete->where([Adherent::PK => $this->login->id]);
            }
            $result = $this->zdb->execute($delete);
            if ($result->count() !== 1) {
                Analog::log(
                    'Saved search #' . $id . ' has not been deleted.',
                    Analog::WARNING
                );
                return false;
            }
            Analog::log(
                'Saved search #' . $id . ' (' . $this->name
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (RuntimeException $re) {
            throw $re;
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to delete saved search ' . $id);
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     */
    public function __get(string $name): mixed
    {
        $forbidden = [];
        $virtuals = ['sparameters'];
        if (
            in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
            switch ($name) {
                case 'creation_date':
                    if ($this->$name != '') {
                        try {
                            $d = new DateTime($this->$name);
                            return $d->format(__("Y-m-d"));
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Logs::exception(
                                $e,
                                'Bad date (' . $this->$name . ')',
                                Analog::INFO
                            );
                            return $this->$name;
                        }
                    }
                    break;
                case 'sparameters':
                    include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
                    $parameters = [];
                    foreach ($this->parameters as $key => $parameter) {
                        if (isset($members_fields[$key])) {
                            $key = $members_fields[$key]['label'];
                        }
                        if (is_array($parameter) || is_object($parameter)) {
                            $parameter = json_encode($parameter);
                        } elseif (RelativeDate::isRelative($parameter)) {
                            $parameter = $this->describeRelativeDate($parameter);
                        }
                        $parameters[$key] = $parameter;
                    }
                    return $parameters;
                default:
                    if (property_exists($this, $name)) {
                        return $this->$name;
                    }
            }
        }

        throw new RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
                $name
            )
        );
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        $forbidden = [];
        $virtuals = ['sparameters'];
        if (
            in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
            return match ($name) {
                'creation_date', 'sparameters' => true,
                default => property_exists($this, $name),
            };
        }
        return false;
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'form':
                if (!in_array($value, $this->getKnownForms())) {
                    $this->errors[] = sprintf(
                        //TRANS: parameter is the form name
                        _T('Unknown form %1$s!'),
                        $value
                    );
                }
                $this->form = $value;
                break;
            case 'parameters':
                if (!is_array($value)) {
                    Analog::log(
                        'Search parameters must be an array!',
                        Analog::ERROR
                    );
                }
                $this->parameters = $value;
                break;
            case 'name':
                if (trim((string)$value) === '') {
                    $this->errors[] = _T("Name cannot be empty!");
                }
                $this->name = $value;
                break;
            case 'author_id':
                $this->author_id = (int)$value;
                break;
            default:
                Analog::log(
                    str_replace(
                        ['%class', '%property'],
                        [self::class, $name],
                        'Unable to set %class property %property'
                    ),
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Human readable relative date, with the date it currently resolves to
     *
     * @param string $value Relative date expression
     */
    private function describeRelativeDate(string $value): string
    {
        global $preferences;

        $description = RelativeDate::describe($value);
        try {
            $date = RelativeDate::resolve($value, $preferences);
            $description .= ' (' . $date->format(__('Y-m-d')) . ')';
        } catch (Throwable) { // no log: not resolvable (any more), keep description only
        }

        return $description;
    }

    /**
     * Get known forms
     *
     * @return array<string>
     */
    public function getKnownForms(): array
    {
        return [
            'Adherent'
        ];
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
