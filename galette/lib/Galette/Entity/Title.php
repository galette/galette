<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Title
 *
 * PHP version 5
 *
 * Copyright © 2013-2021 The Galette Team
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
 * @copyright 2013-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-01-27
 */

namespace Galette\Entity;

use Throwable;
use Analog\Analog;

/**
 * Title
 *
 * @category  Entity
 * @name      Title
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-04
 */

class Title
{
    public const TABLE = 'titles';
    public const PK = 'id_title';

    private $id;
    private $short;
    private $long;

    public const MR = 1;
    public const MRS = 2;
    public const MISS = 3;

    /**
     * Main constructor
     *
     * @param mixed $args Arguments
     */
    public function __construct($args = null)
    {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a title from its identifier
     *
     * @param int $id Identifier
     *
     * @return void
     */
    private function load($id)
    {
        global $zdb;
        try {
            $select = $zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $zdb->execute($select);
            $res = $results->current();

            $this->id = $id;
            $this->short = $res->short_label;
            $this->long = $res->long_label;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading title #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load title from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs($rs)
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        $this->short = $rs->short_label;
        if ($rs->long_label === 'NULL') {
            //mysql's null...
            $this->long = null;
        } else {
            $this->long = $rs->long_label;
        }
    }

    /**
     * Store title in database
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function store($zdb)
    {
        $data = array(
            'short_label'   => strip_tags($this->short),
            'long_label'    => strip_tags($this->long)
        );
        try {
            if ($this->id !== null && $this->id > 0) {
                $update = $zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->id]);
                $zdb->execute($update);
            } else {
                $insert = $zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $zdb->getLastGeneratedValue($this);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing title: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove($zdb)
    {
        $id = (int)$this->id;
        if ($id === self::MR || $id === self::MRS) {
            throw new \RuntimeException(_T("You cannot delete Mr. or Mrs. titles!"));
        }

        try {
            $delete = $zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $zdb->execute($delete);
            Analog::log(
                'Title #' . $id . ' (' . $this->short
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete title ' . $id . ' | ' . $e->getMessage(),
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
    public function __get($name)
    {
        global $lang;

        switch ($name) {
            case 'id':
                return $this->$name;
                break;
            case 'short':
            case 'long':
                if (
                    $name === 'long'
                    && ($this->long == null || trim($this->long) === '')
                ) {
                    $name = 'short';
                }
                return $this->$name;
                break;
            case 'tshort':
            case 'tlong':
                $rname = null;
                if ($name === 'tshort') {
                    $rname = 'short';
                } else {
                    if ($this->long !== null && trim($this->long) !== '') {
                        $rname = 'long';
                    } else {
                        //switch back to short version if long does not exists
                        $rname = 'short';
                    }
                }
                if (isset($lang) && isset($lang[$this->$rname])) {
                    return _T($this->$rname);
                } else {
                    return $this->$rname;
                }
                break;
            default:
                Analog::log(
                    'Unable to get Title property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'short':
            case 'long':
                if (trim($value) === '') {
                    Analog::log(
                        'Trying to set empty value for title' . $name,
                        Analog::WARNING
                    );
                } else {
                    $this->$name = $value;
                }
                break;
            default:
                Analog::log(
                    'Unable to set property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }
}
