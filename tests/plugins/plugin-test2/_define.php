<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Test2 Plugin',    //Name
    desc: 'Test two plugin',         //Short description
    author: 'Johan Cwiklinski',      //Author
    version: '1.0',                  //Version
    compver: GALETTE_COMPAT_VERSION, //Galette compatible version
    route: 'plugin2',                //routing name
    date: '2013-12-15',              //Release date
    acls: [                          //Permissions needed
        'plugin2_root'  => 'member',
        'plugin2_admin' => 'staff'
    ]
);
