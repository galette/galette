<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

use Galette\Access\AccessControl;
use Galette\Core\GaletteMail;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Maps\Coordinates;

/**
 * Actions on members, from lists and member pages
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberActions
{
    /** @var array<int, true>|null members who have a position, once loaded */
    private ?array $located = null;

    /**
     * Constructor
     *
     * @param Login         $login       Login instance
     * @param Preferences   $preferences Preferences instance
     * @param Plugins       $plugins     Plugins instance
     * @param AccessControl $access      Access control
     * @param Coordinates   $coordinates Members positions
     */
    public function __construct(
        private readonly Login $login,
        private readonly Preferences $preferences,
        private readonly Plugins $plugins,
        private readonly AccessControl $access,
        private readonly Coordinates $coordinates
    ) {
    }

    /**
     * Get members list actions
     *
     * @param Adherent $member Current member
     *
     * @return list<MenuEntry>
     */
    public function getListActions(Adherent $member): array
    {
        $actions = [];

        if ($member->canEdit($this->login)) {
            $label = sprintf(
                //TRANS: parameter is the member name
                _T('%1$s: edit information'),
                $member->sname
            );
            $actions[] = new MenuEntry(
                label: $label,
                route: new RouteTarget('editMember', ['id' => $member->id]),
                title: $label,
                icon: 'user edit'
            );
        }

        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $label = sprintf(
                //TRANS: parameter is the member name
                _T('%1$s: contributions'),
                $member->sname
            );
            $actions[] = new MenuEntry(
                label: $label,
                route: new RouteTarget(
                    'contributions',
                    ['type' => 'contributions', 'option' => 'member', 'value' => $member->id]
                ),
                title: $label,
                icon: 'receipt green'
            );

            $label = sprintf(
                //TRANS: parameter is the member name
                _T('%1$s: remove from database'),
                $member->sname
            );
            $actions[] = new MenuEntry(
                label: $label,
                route: new RouteTarget('removeMember', ['id' => $member->id]),
                title: $label,
                icon: 'user times red',
                extraClass: 'delete'
            );
        }

        array_push($actions, ...$this->getLocalizeActions($member));

        if ($this->login->isSuperAdmin()) {
            $label = sprintf(
                //TRANS: parameter is the member name
                _T('Log in as %1$s'),
                $member->sname
            );
            $actions[] = new MenuEntry(
                label: $label,
                route: new RouteTarget('impersonate', ['id' => $member->id]),
                title: $label,
                icon: 'user secret grey'
            );
        }

        foreach ($this->plugins->getProviders(Plugins\MemberActionProviderInterface::class) as $plugin) {
            array_push($actions, ...MenuEntry::listFromArray($plugin->getListActions($member)));
        }
        return $actions;
    }

    /**
     * Get member show actions
     *
     * @param Adherent $member Current member
     *
     * @return list<MenuEntry>
     */
    public function getDetailedActions(Adherent $member): array
    {
        //other core detailed actions are in elements/member_actions.html.twig
        $actions = $this->getLocalizeActions($member);
        foreach ($this->plugins->getProviders(Plugins\MemberActionProviderInterface::class) as $plugin) {
            array_push($actions, ...MenuEntry::listFromArray($plugin->getDetailedActions($member)));
        }
        return $actions;
    }

    /**
     * Get member position action, when maps are enabled
     *
     * Those who can edit the member set its position. Others can only look
     * at an existing one: the page checks it again.
     *
     * @param Adherent $member Current member
     *
     * @return list<MenuEntry>
     */
    private function getLocalizeActions(Adherent $member): array
    {
        if (!$this->preferences->pref_maps_enabled) {
            return [];
        }

        $route = new RouteTarget('mapsLocalizeMember', ['id' => $member->id]);
        if ($member->canEdit($this->login)) {
            $label = _T("Geolocalize");
            $title = sprintf(
                //TRANS: parameter is the member name
                _T('Geolocalize %1$s'),
                $member->sname
            );
        } elseif (isset($this->getLocatedIds()[(int)$member->id])) {
            $label = _T("Show on map");
            $title = sprintf(
                //TRANS: parameter is the member name
                _T('Show %1$s on map'),
                $member->sname
            );
        } else {
            //nothing to show, nothing to set
            return [];
        }

        return [
            new MenuEntry(
                label: $label,
                route: $route,
                title: $title,
                icon: 'map marker alternate grey',
                //pages loading maps libraries open it in a modal
                extraClass: 'maps-localize'
            )
        ];
    }

    /**
     * Get ids of members who have a position, loaded once for a whole list
     *
     * @return array<int, true>
     */
    private function getLocatedIds(): array
    {
        return $this->located ??= $this->coordinates->getLocatedIds();
    }

    /**
     * Get members list batch actions
     *
     * @return list<BatchAction>
     */
    public function getBatchActions(): array
    {
        $actions = [];

        if ($this->access->can('member:mass-edit', login: $this->login)) {
            $actions[] = new BatchAction('masschange', _T('Mass change'), 'user edit blue');
        }
        if ($this->access->can('contribution:mass-create', login: $this->login)) {
            $actions[] = new BatchAction('masscontributions', _T('Mass add contributions'), 'receipt bite green');
        }
        if ($this->access->can('member:mass-edit', login: $this->login) && $this->access->can('member:delete', login: $this->login)) {
            $actions[] = new BatchAction('delete', _T('Delete'), 'user times red');
        }

        if (
            $this->access->can('mailing:send', login: $this->login)
            && $this->preferences->pref_mail_method != GaletteMail::METHOD_DISABLED
        ) {
            $actions[] = new BatchAction('sendmail', _T('Mail'), 'mail bulk');
        }

        if ($this->access->can('member:print', login: $this->login)) {
            $actions[] = new BatchAction('attendance_sheet', _T('Attendance sheet'), 'file alternate');
            $actions[] = new BatchAction('labels__directdownload', _T('Generate labels'), 'address card');
            $actions[] = new BatchAction('cards__directdownload', _T('Generate Member Cards'), 'id badge');
        }

        if ($this->access->can('member:export', login: $this->login)) {
            $actions[] = new BatchAction('csv__directdownload', _T('Export as CSV'), 'file csv');
        }

        foreach ($this->plugins->getProviders(Plugins\MemberActionProviderInterface::class) as $plugin) {
            array_push($actions, ...BatchAction::listFromArray($plugin->getBatchActions()));
        }
        return $actions;
    }
}
