<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Old Plugin', //Name
    desc: 'Test old plugin',    //Short description
    author: 'Johan Cwiklinski', //Author
    version: '1.0',             //Version
    compver: '0.7.0',           //Galette compatible version
);
