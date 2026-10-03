<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Db No Version',   //Name
    desc: 'Test db plugin',          //Short description
    author: 'Johan Cwiklinski',      //Author
    version: '1.0',                  //Version
    compver: GALETTE_COMPAT_VERSION, //Galette compatible version
    route: 'plugdbnover',            //routing name
    date: '2015-01-30',              //release date
    acls: [                          //Permissions needed
        'plugdbnover_root'   => 'member',
        'plugdbnover_admin'  => 'staff'
    ]
);
