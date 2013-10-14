<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions types handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Contributions types handling
 *
 * @category  Entity
 * @name      ContibutionTypes
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

class ContributionsTypes extends Entitled
{
    const DEFAULT_TYPE = 1;
    const TABLE = 'types_cotisation';
    const PK = 'id_type_cotis';
    const LABEL_FIELD = 'libelle_type_cotis';
    const EXT_FIELD = 'cotis_extension';

    const ID_NOT_EXITS = -1;

    protected static $fields = array(
        'id_type_cotis',
        'libelle_type_cotis',
        'cotis_extension'
    );

    protected static $defaults = array(
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
    * @param ResultSet $args Optionnal existing result set
    */
    public function __construct($args = null)
    {
        parent::__construct(
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
    protected function getType()
    {
        return 'contribution type';
    }

    /**
     * Get translated textual representation
     *
     * @return string
     */
    protected function getI18nType()
    {
        return _T("contribution type");
    }

    /**
     * Does current type give membership extension?
     *
     * @return Boolean
     */
    public function isExtension()
    {
        return $this->third;
    }
}
