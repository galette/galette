<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Events\ContribListener;
use Slim\Flash\Messages;
use Slim\Routing\RouteParser;

/**
 * ContribListener recording mails instead of sending them.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RecordingContribListener extends ContribListener
{
    use RecordsListenerMails;

    /**
     * Constructor
     *
     * @param Preferences $preferences Preferences instance
     * @param RouteParser $routeparser RouteParser instance
     * @param History     $history     History instance
     * @param Messages    $flash       Messages instance
     * @param Login       $login       Login instance
     * @param Db          $zdb         Db instance
     */
    public function __construct(
        Preferences $preferences,
        RouteParser $routeparser,
        History $history,
        Messages $flash,
        Login $login,
        Db $zdb
    ) {
        $this->mail_preferences = $preferences;
        parent::__construct(
            preferences: $preferences,
            routeparser: $routeparser,
            history: $history,
            flash: $flash,
            login: $login,
            zdb: $zdb
        );
    }
}
