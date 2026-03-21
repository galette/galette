<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

use ArrayObject;
use Safe\DateTime;
use Galette\Core\Galette;
use Galette\Entity\Attributes\Column;
use RuntimeException;
use Throwable;
use Galette\Core\Db;
use Galette\Core\Login;
use Analog\Analog;

use function Safe\json_encode;

/**
 * Saved search
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property int                  $id
 * @property string               $name
 * @property array<string, mixed> $parameters
 * @property int                  $author_id
 * @property string               $creation_date
 * @property string               $form
 */

class SavedSearch extends AbstractEntity
{
    public const TABLE = 'searches';
    public const PK = 'search_id';

    #[Column(name: 'search_id', insertable: false, updatable: false)]
    protected int $id;

    #[Column(name: 'name')]
    private string $name;

    #[Column(name: 'parameters')]
    /** @var array<string, mixed> */
    private array $parameters = [];

    #[Column(name: 'id_adh')]
    private ?int $author_id = null;

    #[Column(name: 'creation_date')]
    private ?string $creation_date;

    #[Column(name: 'form')]
    private string $form;


    /**
     * Main constructor
     *
     * @param Db                                      $zdb   Database instance
     * @param Login                                   $login Login instance
     * @param ArrayObject<string,int|string>|int|null $args  Arguments
     */
    public function __construct(
        Db $zdb,
        Login $login,
        ArrayObject|int|null $args = null
    ) {
        $this->zdb = $zdb;
        $this->login = $login;
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
    public function load(int $id): static
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);
            if ($this->getLogin()->isSuperAdmin()) {
                $select->where(Adherent::PK . ' IS NULL');
            } else {
                $select->where([Adherent::PK => $this->getLogin()->id]);
            }

            $results = $this->zdb->execute($select);
            /** @var ArrayObject<string, int|string> $res */
            $res = $results->current();

            $this->loadFromRS($res);
            return $this;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading saved search #' . $id . "Message:\n"
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load a saved search from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    public function loadFromRS(ArrayObject $rs): static
    {
        $pk = self::PK;
        $this->id = (int)$rs->$pk;
        $this->name = $rs->name ?? '';
        try {
            $this->parameters = Galette::jsonDecode($rs->parameters);
        } catch (RuntimeException $e) {
            Analog::log(
                'Unable to decode parameters for saved search #' . $this->id
                . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            $this->parameters = [];
        }
        if ($rs->id_adh !== null) {
            $this->author_id = (int)$rs->id_adh;
        }
        $this->creation_date = $rs->creation_date;
        $this->form = $rs->form;
        return $this;
    }

    /**
     * Check and set values
     *
     * @param array<string, mixed> $values   Values to set
     * @param array<string,int>    $required Array of required fields (not used here)
     * @param array<string>        $disabled Array of disabled fields (not used here)
     *
     * @return true|array<string> True if valid, array of errors otherwise
     */
    public function check(array $values, array $required = [], array $disabled = []): bool|array
    {
        $this->errors = [];

        // Sanitize values
        $values = $this->sanitizeValues($values);

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

        if (!isset($this->id) && !$this->getLogin()->isSuperAdmin()) {
            //set author for new searches
            $this->author_id = $this->getLogin()->id;
        }

        return count($this->errors) === 0 ? true : $this->errors;
    }

    /**
     * Instructions before insert
     */
    protected function preInsert(): bool
    {
        // Encode parameters to JSON before insert
        if (!isset($this->creation_date)) {
            $this->creation_date = date('Y-m-d H:i:s');
        }
        return true;
    }

    /**
     * Get data for insert/update formatted for database
     * Override to handle JSON encoding of parameters
     *
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        return [
            'name'              => $this->name,
            'parameters'        => json_encode($this->parameters),
            'id_adh'            => $this->author_id,
            'creation_date'     => $this->creation_date ?? date('Y-m-d H:i:s'),
            'form'              => $this->form
        ];
    }

    /**
     * Store saved search in database
     * Kept for backward compatibility, delegates to save()
     */
    public function store(): bool
    {
        try {
            $data = $this->getData();

            if (!isset($this->id)) {
                // Insert
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
                $this->id = $this->zdb->getLastGeneratedValue($this);
                return true;
            } else {
                // Update
                unset($data['creation_date']); // Don't update creation date
                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->id]);
                $this->zdb->execute($update);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing saved search: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current saved search
     * Delegates to AbstractEntity::delete()
     */
    public function remove(): bool
    {
        $name = $this->name ?? '';
        try {
            $result = $this->delete();
            if ($result) {
                Analog::log(
                    'Saved search #' . $this->id . ' (' . $name
                    . ') deleted successfully.',
                    Analog::INFO
                );
            }
            return $result;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete saved search ' . $this->id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
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
                            Analog::log(
                                'Bad date (' . $this->$name . ') | '
                                . $e->getMessage(),
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
}
