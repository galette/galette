<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Member's table fields categories declarations
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 *
 * @category  Functions
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     0.8.2 - 2014-11-01
 */

use Galette\Entity\Adherent;

$members_fields_cats = array(
    array(
        'id'         => 1,
        'table_name' => Adherent::TABLE,
        'category'   => "Identity:",
        'position'   => 1
    ),
    array(
        'id'         => 2,
        'table_name' => Adherent::TABLE,
        'category'   => "Galette-related data:",
        'position'   => 3
    ),
    array(
        'id'         => 3,
        'table_name' => Adherent::TABLE,
        'category'   => "Contact information:",
        'position'   => 2
    )
);
