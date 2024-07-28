<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Db Upgrade Plugin',   //Name
    desc: 'Test db upgrade plugin',      //Short description
    author: 'Johan Cwiklinski',          //Author
    version: '1.0',                      //Version
    compver: GALETTE_COMPAT_VERSION,     //Galette compatible version
    route: 'plugdbupgrade',              //routing name
    date: '2026-01-01',                  //release date
    acls: [],                            //Permissions needed
    dbver: 0.2,                          //DB version (newer than what is seeded in DB as 0.1)
);
