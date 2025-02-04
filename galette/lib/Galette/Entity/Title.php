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

use ArrayObject;
use Galette\Core\Db;
use Throwable;
use Analog\Analog;

/**
 * Title
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property int $id
 * @property string $short
 * @property ?string $long
 * @property-read string $tshort
 * @property-read string $tlong
 */

class Title
{
    public const TABLE = 'titles';
    public const PK = 'id_title';

    private int $id;
    private string $short;
    private ?string $long;

    public const MR = 1;
    public const MRS = 2;
    public const MISS = 3;

    /**
     * Main constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Arguments
     */
    public function __construct(int|ArrayObject|null $args = null)
    {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load a title from its identifier
     *
     * @param int $id Identifier
     *
     * @return void
     */
    private function load(int $id): void
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
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        $pk = self::PK;
        $this->id = (int)$rs->$pk;
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
    public function store(Db $zdb): bool
    {
        $data = array(
            'short_label'   => strip_tags($this->short),
            'long_label'    => strip_tags($this->long)
        );
        try {
            if (isset($this->id) && $this->id > 0) {
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
    public function remove(Db $zdb): bool
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
    public function __get(string $name): mixed
    {
        global $lang;

        switch ($name) {
            case 'id':
                return $this->$name;
            case 'short':
            case 'long':
                if (
                    $name === 'long'
                    && ($this->long == null || trim($this->long) === '')
                ) {
                    $name = 'short';
                }
                return $this->$name;
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
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
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
        switch ($name) {
            case 'id':
            case 'short':
            case 'long':
            case 'tshort':
            case 'tlong':
                return true;
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
    public function __set(string $name, mixed $value): void
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
