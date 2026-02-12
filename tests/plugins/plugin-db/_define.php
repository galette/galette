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

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Db Plugin',       //Name
    desc: 'Test db plugin',          //Short description
    author: 'Johan Cwiklinski',      //Author
    version: '1.0',                  //Version
    compver: GALETTE_COMPAT_VERSION, //Galette compatible version
    route: 'plugdb',                 //routing name
    date: '2015-01-30',              //release date
    acls: [                          //Permissions needed
        'plugdb_root'   => 'member',
        'plugdb_admin'  => 'staff'
    ]
);
