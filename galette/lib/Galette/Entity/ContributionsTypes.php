<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions types handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2023 The Galette Team
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
 * @copyright 2007-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

use Galette\Core\Db;
use ArrayObject;

/**
 * Contributions types handling
 *
 * @category  Entity
 * @name      ContibutionTypes
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

class ContributionsTypes extends Entitled
{
    public const DEFAULT_TYPE = 1;
    public const TABLE = 'types_cotisation';
    public const PK = 'id_type_cotis';
    public const LABEL_FIELD = 'libelle_type_cotis';
    public const EXT_FIELD = 'cotis_extension';

    public const ID_NOT_EXITS = -1;

    public static array $fields = array(
        'id'        => 'id_type_cotis',
        'libelle'   => 'libelle_type_cotis',
        'third'     => 'cotis_extension'
    );

    protected static array $defaults = array(
        array('id' => 1, 'libelle' => 'annual fee', 'extension' => '1'),
        array('id' => 2, 'libelle' => 'reduced annual fee', 'extension' => '1'),
        array('id' => 3, 'libelle' => 'company fee', 'extension' => '1'),
        array('id' => 4, 'libelle' => 'donation in kind', 'extension' => 0),
        array('id' => 5, 'libelle' => 'donation in money', 'extension' => 0),
        array('id' => 6, 'libelle' => 'partnership', 'extension' => 0),
        array('id' => 7, 'libelle' => 'annual fee (to be paid)', 'extension' => '1')
    );

    /**
     * Default constructor
     *
     * @param Db                   $zdb  Database
     * @param int|ArrayObject|null $args Optional existing result set
     */
    public function __construct(Db $zdb, int|ArrayObject $args = null)
    {
        parent::__construct(
            $zdb,
            self::TABLE,
            self::PK,
            self::LABEL_FIELD,
            self::EXT_FIELD,
            Contribution::TABLE,
            $args
        );
        $this->order_field = self::PK;
    }

    /**
     * Get textual type representation
     *
     * @return string
     */
    protected function getType(): string
    {
        return 'contribution type';
    }

    /**
     * Get translated textual representation
     *
     * @return string
     */
    public function getI18nType(): string
    {
        return _T("contribution type");
    }

    /**
     * Does current type give membership extension?
     *
     * @return boolean
     */
    public function isExtension(): bool
    {
        return (bool)$this->third;
    }
}
