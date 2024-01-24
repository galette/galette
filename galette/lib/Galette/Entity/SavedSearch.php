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

namespace Galette\Entity;

use ArrayObject;
use Throwable;
use Galette\Core\Db;
use Galette\Core\Login;
use Analog\Analog;

/**
 * Saved search
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $id
 * @property string $name
 * @property array $parameters
 * @property integer $author_id
 * @property string $creation_date
 * @property string $form
 */

class SavedSearch
{
    public const TABLE = 'searches';
    public const PK = 'search_id';

    private Db $zdb;
    private int $id;
    private string $name;
    /** @var array<string, mixed> */
    private array $parameters = [];
    private ?int $author_id = null;
    private ?string $creation_date;
    private string $form;

    private Login $login;
    /** @var array<string> */
    private array $errors = [];

    /**
     * Main constructor
     *
     * @param Db                                      $zdb   Database instance
     * @param Login                                   $login Login instance
     * @param ArrayObject<string,int|string>|int|null $args  Arguments
     */
    public function __construct(Db $zdb, Login $login, ArrayObject|int $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->creation_date = date('Y-m-d H:i:s');

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a saved search from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
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
            /** @var ArrayObject<string, int|string> $res */
            $res = $results->current();

            $this->loadFromRs($res);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading saved search #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load a saved search from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs(ArrayObject $rs): void
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        $this->name = $rs->name;
        $this->parameters = json_decode($rs->parameters, true);
        $this->author_id = $rs->id_adh;
        $this->creation_date = $rs->creation_date;
        $this->form = $rs->form;
    }

    /**
     * Check and set values
     *
     * @param array<string, mixed> $values Values to set
     *
     * @return boolean
     */
    public function check(array $values): bool
    {
        $this->errors = [];
        $mandatory = [
            'form'  => _T('Form is mandatory!')
        ];

        foreach ($values as $key => $value) {
            if (in_array($key, ['nbshow', 'page'])) {
                continue;
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

        return (count($this->errors) === 0);
    }

    /**
     * Store saved search in database
     *
     * @return boolean
     */
    public function store(): bool
    {
        $parameters = json_encode($this->parameters);
        $data = array(
            'name'              => $this->name,
            'parameters'        => $parameters,
            'id_adh'            => $this->author_id,
            'creation_date'     => ($this->creation_date !== null ? $this->creation_date : date('Y-m-d H:i:s')),
            'form'              => $this->form
        );

        try {
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($data);
            $add = $this->zdb->execute($insert);
            if (!$add->count() > 0) {
                Analog::log('Not stored!', Analog::ERROR);
                return false;
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing saved search: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current saved search
     *
     * @return boolean
     */
    public function remove(): bool
    {
        $id = $this->id;
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $this->zdb->execute($delete);
            Analog::log(
                'Saved search #' . $id . ' (' . $this->name
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete saved search ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get(string $name)
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
                            $d = new \DateTime($this->$name);
                            return $d->format(__("Y-m-d"));
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$name . ') | ' .
                                $e->getMessage(),
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
                        //@phpstan-ignore-next-line
                        if (isset($members_fields[$key])) {
                            //@phpstan-ignore-next-line
                            $key = $members_fields[$key]['label'];
                        }
                        if (is_array($parameter) || is_object($parameter)) {
                            $parameter = json_encode($parameter);
                        }
                        $parameters[$key] = $parameter;
                    }
                    return $parameters;
                default:
                    if (!property_exists($this, $name)) {
                        Analog::log(
                            "Unknown property '$name'",
                            Analog::WARNING
                        );
                        return null;
                    } else {
                        return $this->$name;
                    }
            }
        }
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     *
     * @return bool
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
            switch ($name) {
                case 'creation_date':
                case 'sparameters':
                    return true;
                default:
                    return property_exists($this, $name);
            }
        }
        return false;
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'form':
                if (!in_array($value, $this->getKnownForms())) {
                    $this->errors[] = str_replace('%form', $value, _T("Unknown form %form!"));
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
                if (trim($value) === '') {
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
