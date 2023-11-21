<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract entity class for galette
 *
 * PHP version 5
 *
 * Copyright © 2022 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 1.0.0dev - 2022-06-12
 */

namespace Galette\Entity;

use Analog\Analog;
use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Core\Login;

/**
 * Abstract entity class for galette
 *
 * @category  Entity
 * @name      AbstractEntity
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 1.0.0dev - 2022-06-12
 *
 * @property integer $id
 *
 */
abstract class AbstractEntity
{
    //public const TABLE = 'table_name_without_prefix';
    //public const PK = 'primary_key_field';

    /** @var integer */
    private int $id;

    /** @var array Load dependencies list and default state */
    private array $deps = [];
    /** @var array Fields configuration, labels, and so on */
    protected array $fields = [];

    /** @var Db */
    protected Db $zdb;
    /** @var Preferences */
    protected Preferences $preferences;
    /** @var array */
    protected array $errors;

    /**
     * Default constructor
     *
     * @param Db               $zdb  Database instance
     * @param mixed            $args Either a ResultSet row, its id or its
     *                               login or its email for to load s specific
     *                               member, or null to just instantiate object
     * @param false|array|null $deps Dependencies configuration, see Adherent::$_deps
     */
    public function __construct(Db $zdb, $args = null, $deps = null)
    {
        $this->zdb = $zdb;

        if ($deps !== null) {
            if (is_array($deps)) {
                $this->deps = array_merge(
                    $this->deps,
                    $deps
                );
            } elseif ($deps === false) {
                //no dependencies
                $this->disableAllDeps();
            } else {
                Analog::log(
                    '$deps should be an array, ' . gettype($deps) . ' given!',
                    Analog::WARNING
                );
            }
        }

        if ((int)$args > 0) {
            $this->load($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        } elseif (is_string($args)) {
            $this->loadFromLoginOrMail($args);
        }
    }

    /**
     * Loads an entity from its id
     *
     * @param int $id the identifier for the entity to load
     *
     * @return bool
     */
    abstract public function load(int $id): bool;

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject $r the resultset row
     *
     * @return void
     */
    abstract protected function loadFromRS($r): void;

    /**
     * Get field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    protected function getFieldLabel(string $field): string
    {
        $label = $this->fields[$field]['label'] ?? '';
        //replace "&nbsp;"
        $label = str_replace('&nbsp;', ' ', $label ?? '');

        return trim(
            trim(
                $label,
                ':'
            ) //trim ending ":"
        ); // trim spaces
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array
     */
    public static function getDbFields(Db $zdb): array
    {
        /*$columns = $zdb->getColumns(static::TABLE);
        $fields = array();
        foreach ($columns as $col) {
            $fields[] = $col->getName();
        }
        return $fields;*/
    }

    /**
     * Set dependencies
     *
     * @param Preferences $preferences Preferences instance
     * @param array       $fields      Members fields configuration
     * @param History     $history     History instance
     *
     * @return void
     */
    /*public function setDependencies(
        Preferences $preferences,
        array $fields,
        History $history
    ) {
        $this->preferences = $preferences;
        $this->fields = $fields;
        $this->history = $history;
    }*/

    /**
     * Check posted values validity
     *
     * @param array $values   All values to check, basically the $_POST array
     *                        after sending the form
     * @param array $required Array of required fields
     * @param array $disabled Array of disabled fields
     *
     * @return true|array
     */
    abstract public function check(array $values, array $required, array $disabled);

    /**
     * Validate data for given key
     * Set valid data in current object, also resets errors list
     *
     * @param string $field  Field name
     * @param mixed  $value  Value we want to set
     * @param array  $values All values, for some references
     *
     * @return void
     */
    abstract public function validate(string $field, $value, array $values): void;

    /**
     * Store the member
     *
     * @return boolean
     */
    abstract public function store(): bool;

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed
     */
    abstract public function __get(string $name);

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }

    /**
     * Get current errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Can current logged-in user create member
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    abstract public function canCreate(Login $login): bool;

    /**
     * Can current logged-in user edit member
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    abstract public function canEdit(Login $login): bool;

    /**
     * Can current logged-in user display member
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    abstract public function canShow(Login $login): bool;
}
