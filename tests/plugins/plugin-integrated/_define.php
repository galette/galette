<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Integrated Plugin', //Name
    desc: 'Test plugin now part of the core', //Short description
    author: 'Johan Cwiklinski', //Author
    version: '9.0',             //Version
    compver: '9.0.0',           //Galette compatible version
    route: 'maps',              //routing name, listed as integrated
);
