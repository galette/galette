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

$this->register(
    'Galette Unversionned Plugin', //Name
    'Test unversionned plugin', //Short description
    'Johan Cwiklinski',         //Author
    '1.0',                      //Version
    null,                       //Galette compatible version
    'pluginunver',              //routing name
    '2016-10-19',               //Release date
    [   //Permissions needed
        'pluginunver_root'  => 'admin'
    ]
);
