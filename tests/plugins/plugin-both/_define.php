<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

//ignored: the PluginDefinition attribute takes precedence
/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Both Plugin (legacy)', //Name
    desc: 'Test legacy definition',       //Short description
    author: 'Johan Cwiklinski',           //Author
    version: '0.1',                       //Version
    compver: '0.7.0',                     //Galette compatible version
    route: 'bothlegacy',                  //routing name
);
