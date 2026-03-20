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
use Galette\Core\Db;
use Galette\Entity\Attributes\Column;
use Throwable;
use Analog\Analog;

/**
 * Title
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property      int     $id
 * @property      string  $short
 * @property      ?string $long
 * @property-read string  $tshort
 * @property-read string  $tlong
 */

class Title extends AbstractEntity
{
    public const TABLE = 'titles';
    public const PK = 'id_title';

    #[Column(self::PK)]
    protected int $id;

    #[Column('short_label')]
    private string $short;
    #[Column('long_label')]
    private ?string $long = null;

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
     * Instructions to be processed before insert
     */
    protected function preInsert(): bool
    {
        $this->short = strip_tags($this->short);
        $this->long = strip_tags((string)$this->long);
        return true;
    }

    /**
     * Instructions to be processed before delete
     */
    protected function preDelete(): bool
    {
        $id = $this->id;
        if ($id === self::MR || $id === self::MRS) {
            throw new \RuntimeException(_T("You cannot delete Mr. or Mrs. titles!"));
        }
        return true;
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     */
    public function remove(Db $zdb): bool
    {
        $id = $this->id;
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
     */
    public function __get(string $name): mixed
    {
        global $lang;

        switch ($name) {
            case 'id':
                return $this->getId();
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
                } elseif ($this->long !== null && trim($this->long) !== '') {
                    $rname = 'long';
                } else {
                    //switch back to short version if long does not exists
                    $rname = 'short';
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
        return match ($name) {
            'id', 'short', 'long', 'tshort', 'tlong' => true,
            default => false,
        };
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
            case 'short':
                if (trim((string)$value) === '') {
                    Analog::log(
                        'Trying to set empty value for title' . $name,
                        Analog::WARNING
                    );
                } else {
                    $this->$name = $value;
                }
                break;
            case 'long':
                $this->$name = $value;
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
