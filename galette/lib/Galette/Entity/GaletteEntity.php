<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Entity abstract class for Galette
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.5dev - 2020-12-12
 */

namespace Galette\Entity;

/**
 * Entity abstract class for Galette
 *
 * @category  Entity
 * @name      GaletteEntity
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     Available since 0.9.5dev - 2020-12-12
 */
abstract class GaletteEntity
{
    /**
     * Entities are often based on database tables.
     * In that case, following constants must be daclared:
     *
     * public const TABLE = 'adherents';
     * public const PK = 'id_adh';
     */

    /**
     * @Inject("zdb")
     */
    protected $zdb;

    /**
     * @Inject("preferences")
     */
    protected $preferences;

    /**
     * @Inject("history")
     */
    protected $history;

    private $deps = array(
        /*'picture'   => true,
        'groups'    => true,
        'dues'      => true,
        'parent'    => false,
        'children'  => false,
        'dynamics'  => false*/
    );

    private $errors = [];

    /** FIELDS */
    protected $creation_date;
    protected $modification_date;
    /** END FIELDS */

    /**
     * Loads a member from its id
     *
     * @param integer $id ID (primary key) of the object to load
     *
     * @return boolean
     */
    abstract public function load(int $id): bool;

    /**
     * Populate object from a resultset row
     *
     * @param ResultSet $r the resultset row
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
    protected function getFieldLabel($field)
    {
        $label = $this->fields[$field]['label'];
        //remove trailing ':' and then nbsp (for french at least)
        $label = trim(trim($label, ':'), '&nbsp;');
        return $label;
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array
     */
    public static function getDbFields(Db $zdb)
    {
        $columns = $zdb->getColumns(self::TABLE);
        $fields = array();
        foreach ($columns as $col) {
            $fields[] = $col->getName();
        }
        return $fields;
    }

    /**
     * Update modification date
     *
     * @return void
     */
    /*protected function updateModificationDate()
    {
        try {
            $modif_date = date('Y-m-d');
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array('date_modif_adh' => $modif_date)
            )->where(self::PK . '=' . $this->_id);

            $edit = $this->zdb->execute($update);
            $this->_modification_date = $modif_date;
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong updating modif date :\'( | ' .
                $e->getMessage() . "\n" . $e->getTraceAsString(),
                Analog::ERROR
            );
        }
    }*/

    /**
     * Get current errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
