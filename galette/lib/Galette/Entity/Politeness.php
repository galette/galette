<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * politeness.class.php, 4 mars 2009
 *
 * PHP version 5
 *
 * Copyright © 2009-2012 The Galette Team
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
 * @copyright 2009-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-04
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Politeness class for galette
 *
 * @category  Entity
 * @name      Politeness
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-04
 */

class Politeness
{
    const MR = 1;
    const MRS = 2;
    const MISS = 3;

    /**
    * Get the list of all politenesses
    *
    * @return array
    */
    public static function getList()
    {
        return array(
            self::MR        =>  _T("Mister"),
            self::MRS       =>  _T("Mrs"),
            self::MISS      =>  _T("Miss")
        );
    }

    /**
    * Get the politeness
    *
    * @param integer $politeness The politeness to retrieve
    *
    * @return translated politeness
    */
    public static function getPoliteness($politeness)
    {
        switch( $politeness ){
        case self::MR:
            return _T("Mr.");
            break;
        case self::MRS:
            return _T("Mrs.");
            break;
        case self::MISS:
            return _T("Miss.");
            break;
        default:
            return '';
            break;
        }
    }
}
