<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig;

use Galette\Navigation\Dashboards;
use Galette\Navigation\MainMenu;
use Galette\Navigation\MemberActions;
use Galette\Navigation\PublicMenu;
use Galette\Navigation\ReleaseNotices;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig navigation extension for Galette
 *
 * Provides menus, dashboards and members actions to templates
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class NavigationExtension extends AbstractExtension
{
    /**
     * Constructor
     *
     * @param MainMenu       $mainMenu      Main menu
     * @param PublicMenu     $publicMenu    Public pages menu
     * @param Dashboards     $dashboards    Dashboards
     * @param MemberActions  $memberActions Members actions
     * @param ReleaseNotices $releases      New releases
     */
    public function __construct(
        private readonly MainMenu $mainMenu,
        private readonly PublicMenu $publicMenu,
        private readonly Dashboards $dashboards,
        private readonly MemberActions $memberActions,
        private readonly ReleaseNotices $releases
    ) {
    }

    /**
     * Get functions
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('main_menus', $this->mainMenu->getAllMenus(...)),
            new TwigFunction('public_menus', $this->publicMenu->getMenus(...)),
            new TwigFunction('dashboards', $this->dashboards->getEntries(...)),
            new TwigFunction('my_dashboards', $this->dashboards->getMyEntries(...)),
            new TwigFunction('member_list_actions', $this->memberActions->getListActions(...)),
            new TwigFunction('member_detailed_actions', $this->memberActions->getDetailedActions(...)),
            new TwigFunction('members_batch_actions', $this->memberActions->getBatchActions(...)),
            new TwigFunction('new_release', $this->releases->getNewRelease(...)),
        ];
    }
}
