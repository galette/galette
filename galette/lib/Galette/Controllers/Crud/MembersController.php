<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Galette\DynamicFields\Boolean;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\GaletteMail;
use Galette\Core\Gaptcha;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Entity\Group;
use Galette\Entity\Status;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Social;
use Galette\Filters\AdvancedMembersList;
use Galette\Filters\MembersList;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\Repository\PaymentTypes;
use Galette\Repository\Titles;
use Analog\Analog;

/**
 * Galette members controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class MembersController extends CrudController
{
    private bool $is_self_membership = false;

    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function add(Request $request, Response $response): Response
    {
        return $this->edit($request, $response, null, 'add');
    }

    /**
     * Add child page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function addChild(Request $request, Response $response): Response
    {
        return $this->edit($request, $response, null, 'addchild');
    }

    /**
     * Self subscription page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function selfSubscribe(Request $request, Response $response): Response
    {
        if (!$this->preferences->pref_bool_selfsubscribe || $this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('slash'));
        }

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $member = new Adherent($this->zdb);
            $member->enableDep('dynamics');
        }

        //mark as self membership
        $member->setSelfMembership();

        // flagging required fields
        $fc = $this->fields_config;
        $form_elements = $fc->getFormElements($this->login, true, true);

        // members
        $m = new Members();
        $members = $m->getDropdownMembers(
            $this->zdb,
            $this->login,
            $member->hasParent() ? $member->parent->id : null
        );

        $params = [
            'members' => [
                'filters'   => $m->getFilters(),
                'count'     => $m->getCount()
            ]
        ];

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        $gaptcha = new Gaptcha($this->i18n);
        $this->session->gaptcha = $gaptcha;

        $titles = new Titles($this->zdb);

        // display page
        $this->view->render(
            $response,
            'pages/member_form.html.twig',
            array(
                'page_title'        => _T("Subscription"),
                'parent_tpl'        => 'public_page.html.twig',
                'member'            => $member,
                'self_adh'          => true,
                'autocomplete'      => true,
                'osocials'          => new Social($this->zdb),
                // pseudo random int
                'time'              => time(),
                'titles_list'       => $titles->getList(),
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens'],
                //self_adh specific
                'gaptcha'           => $gaptcha
            ) + $params
        );
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->store($request, $response);
    }

    /**
     * Self subscription add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doSelfSubscribe(Request $request, Response $response): Response
    {
        $this->setSelfMembership();
        return $this->doAdd($request, $response);
    }


    /**
     * Duplicate action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id_adh   Member ID to duplicate
     *
     * @return Response
     */
    public function duplicate(Request $request, Response $response, int $id_adh): Response
    {
        $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true, 'parent' => true]);
        $adh->setDuplicate();

        //store entity in session
        $this->session->member = $adh;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('addMember'));
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Display member card
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Member ID
     *
     * @return Response
     */
    public function show(Request $request, Response $response, int $id): Response
    {
        $member = new Adherent($this->zdb);
        $member
            ->enableAllDeps()
            ->load($id);

        if (!$member->canShow($this->login)) {
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('me')
                );
        }

        if ($member->id == null) {
            //member does not exist!
            $this->flash->addMessage(
                'error_detected',
                str_replace('%id', (string)$id, _T("No member #%id."))
            );

            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                );
        }

        // flagging fields visibility
        $fc = $this->fields_config;
        $display_elements = $fc->getDisplayElements($this->login);

        // display page
        $this->view->render(
            $response,
            'pages/member_show.html.twig',
            array(
                'page_title'        => _T("Member Profile"),
                'member'            => $member,
                'pref_lang'         => $this->i18n->getNameFromId($member->language),
                'pref_card_self'    => $this->preferences->pref_card_self,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'display_elements'  => $display_elements,
                'osocials'          => new Social($this->zdb),
                'navigate'          => $this->handleNavigationLinks($member->id)
            )
        );
        return $response;
    }

    /**
     * Own card show
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function showMe(Request $request, Response $response): Response
    {
        if ($this->login->isSuperAdmin()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('slash'));
        }
        return $this->show($request, $response, $this->login->id);
    }

    /**
     * Public pages (trombinoscope, public list)
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param string|integer|null $value    Value of the option
     * @param string|null         $type     List type (either list or trombi)
     *
     * @return Response
     */
    public function publicList(
        Request $request,
        Response $response,
        string $option = null,
        string|int $value = null,
        string $type = null
    ): Response {
        $varname = $this->getFilterName($this->getDefaultFilterName(), ['prefix' => 'public', 'suffix' => $type]);
        if (isset($this->session->$varname)) {
            $filters = $this->session->$varname;
        } else {
            $filters = new MembersList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $m = new Members($filters);
        $members = $m->getPublicList($type === 'trombi');

        $this->session->$varname = $filters;

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        // display page
        $this->view->render(
            $response,
            ($type === 'list' ? 'pages/members_public_list' : 'pages/members_public_gallery') . '.html.twig',
            array(
                'page_title'    => ($type === 'list' ? _T("Members list") : _T('Trombinoscope')),
                'additionnal_html_class'    => ($type === 'list' ? '' : 'trombinoscope'),
                'type'          => $type,
                'members'       => $members,
                'nb_members'    => $m->getCount(),
                'filters'       => $filters,
                // pseudo random int
                'time'              => time(),
            )
        );
        return $response;
    }

    /**
     * Public pages (trombinoscope, public list)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $type     Type
     *
     * @return Response
     */
    public function filterPublicList(Request $request, Response $response, string $type): Response
    {
        $post = $request->getParsedBody();

        $varname = $this->getFilterName($this->getDefaultFilterName(), ['prefix' => 'public', 'suffix' => $type]);
        if (isset($this->session->$varname)) {
            $filters = $this->session->$varname;
        } else {
            $filters = new MembersList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = (int)$post['nbshow'];
            }
        }

        $this->session->$varname = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('publicList', ['type' => $type]));
    }

    /**
     * Members list
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function list(Request $request, Response $response, string $option = null, int|string $value = null): Response
    {
        if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName())})) {
            $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())};
        } else {
            $filters = new MembersList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $members = new Members($filters);

        $members_list = array();
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $members_list = $members->getMembersList(true);
        } else {
            $members_list = $members->getManagedMembersList(true);
        }

        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);
        $filters->setViewCommonsFilters($this->preferences, $this->view);

        $this->session->{$this->getFilterName($this->getDefaultFilterName())} = $filters;

        // display page
        $this->view->render(
            $response,
            'pages/members_list.html.twig',
            array(
                'page_title'            => _T("Members management"),
                'require_mass'          => true,
                'members'               => $members_list,
                'filter_groups_options' => $groups_list,
                'nb_members'            => $members->getCount(),
                'filters'               => $filters,
                'adv_filters'           => $filters instanceof AdvancedMembersList,
                'galette_list'          => $this->lists_config->getDisplayElements($this->login)
            )
        );
        return $response;
    }

    /**
     * Members filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())} ?? new MembersList();

        //reinitialize filters
        if (isset($post['clear_filter'])) {
            $filters = new MembersList();
        } elseif (isset($post['clear_adv_filter'])) {
            $this->session->{$this->getFilterName($this->getDefaultFilterName())} = null;
            unset($this->session->{$this->getFilterName($this->getDefaultFilterName())});

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('advanced-search'));
        } elseif (isset($post['adv_criteria'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('advanced-search'));
        } else {
            //string to filter
            if (isset($post['filter_str'])) { //filter search string
                $filters->filter_str = stripslashes(
                    htmlspecialchars($post['filter_str'], ENT_QUOTES)
                );
            }
            //field to filter
            if (isset($post['field_filter'])) {
                if (is_numeric($post['field_filter'])) {
                    $filters->field_filter = $post['field_filter'];
                }
            }
            //membership to filter
            if (isset($post['membership_filter'])) {
                if (is_numeric($post['membership_filter'])) {
                    $filters->membership_filter
                        = $post['membership_filter'];
                }
            }
            //account status to filter
            if (isset($post['filter_account'])) {
                if (is_numeric($post['filter_account'])) {
                    $filters->filter_account = $post['filter_account'];
                }
            }
            //email filter
            if (isset($post['email_filter'])) {
                $filters->email_filter = (int)$post['email_filter'];
            }
            //group filter
            if (isset($post['group_filter'])) {
                if ($post['group_filter'] > 0) {
                    $filters->group_filter = (int)$post['group_filter'];
                } else {
                    $filters->group_filter = null;
                }
            }
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = (int)$post['nbshow'];
            }

            if (isset($post['advanced_filtering'])) {
                if (!$filters instanceof AdvancedMembersList) {
                    $filters = new AdvancedMembersList($filters);
                }
                //Advanced filters
                $filters->reinit();
                unset($post['advanced_filtering']);
                $freed = false;
                foreach ($post as $k => $v) {
                    if (strpos($k, 'free_', 0) === 0) {
                        if (!$freed) {
                            $i = 0;
                            foreach ($post['free_field'] as $f) {
                                if (
                                    trim($f) !== ''
                                    && trim($post['free_text'][$i]) !== ''
                                ) {
                                    $fs_search = htmlspecialchars($post['free_text'][$i], ENT_QUOTES);
                                    $log_op
                                        = (int)$post['free_logical_operator'][$i];
                                    $qry_op
                                        = (int)$post['free_query_operator'][$i];
                                    $type = (int)$post['free_type'][$i];
                                    $fs = array(
                                        'idx'       => $i,
                                        'field'     => $f,
                                        'type'      => $type,
                                        'search'    => $fs_search,
                                        'log_op'    => $log_op,
                                        'qry_op'    => $qry_op
                                    );
                                    $filters->free_search = $fs;
                                }
                                $i++;
                            }
                            $freed = true;
                        }
                    } elseif ($k == 'groups_search') {
                        $i = 0;
                        $filters->groups_search_log_op = (int)$post['groups_logical_operator'];
                        foreach ($post['groups_search'] as $g) {
                            if (trim($g) !== '') {
                                $gs = array(
                                    'idx'       => $i,
                                    'group'     => $g
                                );
                                $filters->groups_search = $gs;
                            }
                            $i++;
                        }
                    } else {
                        switch ($k) {
                            case 'contrib_min_amount':
                            case 'contrib_max_amount':
                                if (trim($v) !== '') {
                                    $v = (float)$v;
                                } else {
                                    $v = null;
                                }
                                break;
                        }
                        $filters->$k = $v;
                    }
                }
            }
        }

        if (isset($post['savesearch'])) {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'saveSearch',
                        $post
                    )
                );
        }

        $this->session->{$this->getFilterName($this->getDefaultFilterName())} = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('members'));
    }

    /**
     * Advanced search page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function advancedSearch(Request $request, Response $response): Response
    {
        if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName())})) {
            $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())};
            if (!$filters instanceof AdvancedMembersList) {
                $filters = new AdvancedMembersList($filters);
            }
        } else {
            $filters = new AdvancedMembersList();
        }

        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        //we want only visible fields
        $fields = $this->members_fields;
        $fc = $this->fields_config;
        $fc->filterVisible($this->login, $fields);

        //dynamic fields
        $member = new Adherent($this->zdb);
        $member
            ->disableAllDeps()
            ->enableDep('dynamics')
            ->loadFromLoginOrMail($this->login->login);
        $adh_dynamics = new DynamicFieldsHandle($this->zdb, $this->login, $member);

        $contrib = new Contribution($this->zdb, $this->login);
        $contrib_dynamics = new DynamicFieldsHandle($this->zdb, $this->login, $contrib);

        //Status
        $statuts = new Status($this->zdb);

        //Contributions types
        $ct = new ContributionsTypes($this->zdb);

        //Payment types
        $ptypes = new PaymentTypes(
            $this->zdb,
            $this->preferences,
            $this->login
        );
        $ptlist = $ptypes->getList();

        $filters->setViewCommonsFilters($this->preferences, $this->view);

        $social = new Social($this->zdb);
        $types = $member->getMemberRegisteredTypes();
        $social_types = [];
        foreach ($types as $type) {
            $social_types[$type] = $social->getSystemType($type);
        }

        // display page
        $this->view->render(
            $response,
            'pages/advanced_search.html.twig',
            array(
                'page_title'            => _T("Advanced search"),
                'filter_groups_options' => $groups_list,
                'search_fields'         => $fields,
                'adh_dynamics'          => $adh_dynamics->getSearchFields(),
                'contrib_dynamics'      => $contrib_dynamics->getSearchFields(),
                'adh_socials'           => $social_types,
                'statuts'               => $statuts->getList(),
                'contributions_types'   => $ct->getList(),
                'filters'               => $filters,
                'payments_types'        => $ptlist
            )
        );
        return $response;
    }

    /**
     * Members list for ajax
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param string|integer|null $value    Value of the option
     *
     * @return Response
     */
    public function ajaxList(Request $request, Response $response, string $option = null, string|int $value = null): Response
    {
        $post = $request->getParsedBody();

        $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['prefix' => 'ajax'])} ?? new MembersList();

        if ($option == 'page') {
            $filters->current_page = (int)$value;
        }

        //numbers of rows to display
        if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
            $filters->show = (int)$post['nbshow'];
        }

        $members = new Members($filters);
        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            if ($this->login->isGroupManager()) {
                $members_list = $members->getManagedMembersList(true);
            } else {
                Analog::log(
                    str_replace(
                        ['%id', '%login'],
                        [$this->login->id, $this->login->login],
                        'Trying to list group members without access from #%id (%login)'
                    ),
                    Analog::ERROR
                );
                throw new \Exception('Access denied.');
            }
        } else {
            $members_list = $members->getMembersList(true);
        }

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['prefix' => 'ajax'])} = $filters;

        $selected_members = null;
        $unreachables_members = null;
        if (!isset($post['from'])) {
            $mailing = $this->session->mailing;
            if (!isset($post['members'])) {
                $selected_members = $mailing->recipients;
                $unreachables_members = $mailing->unreachables;
            } else {
                $m = new Members();
                $selected_members = $m->getArrayList($post['members']);
                if (isset($post['unreachables']) && is_array($post['unreachables'])) {
                    $unreachables_members = $m->getArrayList($post['unreachables']);
                }
            }
        } else {
            switch ($post['from']) {
                case 'groups':
                    if (!isset($post['gid'])) {
                        Analog::log(
                            'Trying to list group members with no group id provided',
                            Analog::ERROR
                        );
                        throw new \Exception('A group id is required.');
                    }
                    if (!isset($post['members'])) {
                        $group = new Group((int)$post['gid']);
                        $selected_members = array();
                        if (!isset($post['mode']) || $post['mode'] == 'members') {
                            $selected_members = $group->getMembers();
                        } elseif ($post['mode'] == 'managers') {
                            $selected_members = $group->getManagers();
                        } else {
                            Analog::log(
                                'Trying to list group members with unknown mode',
                                Analog::ERROR
                            );
                            throw new \Exception('Unknown mode.');
                        }
                    } else {
                        $m = new Members();
                        $selected_members = $m->getArrayList($post['members']);
                        if (isset($post['unreachables']) && is_array($post['unreachables'])) {
                            $unreachables_members = $m->getArrayList($post['unreachables']);
                        }
                    }
                    break;
                case 'attach':
                    if (!isset($post['id_adh'])) {
                        throw new \RuntimeException(
                            'Current selected member must be excluded while attaching!'
                        );
                    }
                    break;
            }
        }

        $params = [
            'filters'               => $filters,
            'members_list'          => $members_list,
            'selected_members'      => $selected_members,
            'unreachables_members'  => $unreachables_members
        ];

        if (isset($post['multiple'])) {
            $params['multiple'] = true;
        }

        if (isset($post['gid'])) {
            $params['the_id'] = (int)$post['gid'];
        }

        if (isset($post['id_adh'])) {
            $params['excluded'] = (int)$post['id_adh'];
        }

        // display page
        $this->view->render(
            $response,
            'elements/ajax_members.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Batch actions handler
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function handleBatch(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if (isset($post['entries_sel'])) {
            if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName())})) {
                $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())};
            } else {
                $filters = new MembersList();
            }

            $filters->selected = $post['entries_sel'];
            $knowns = [
                'cards' => 'pdf-members-cards',
                'labels' => 'pdf-members-labels',
                'sendmail' => 'mailing',
                'attendance_sheet' => 'attendance_sheet_details',
                'csv' => 'csv-memberslist',
                'delete' => 'removeMembers',
                'masschange' => 'masschangeMembers',
                'masscontributions' => 'massAddContributionsChooseType'
            ];

            foreach ($knowns as $known => $redirect_url) {
                if (isset($post[$known])) {
                    $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => $known])} = $filters;
                    $redirect_url = $this->routeparser->urlFor($redirect_url);
                    if ($known === 'sendmail') {
                        $redirect_url .= '?mailing_new=new';
                    }
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $redirect_url);
                }
            }

            throw new \RuntimeException('Does not know what to batch :(');
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No member was selected, please check at least one name.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('members'));
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?integer $id       Member id/array of members id
     * @param string   $action   null or 'add'
     *
     * @return Response
     */
    public function edit(
        Request $request,
        Response $response,
        int $id = null,
        string $action = 'edit'
    ): Response {
        //instantiate member object
        $member = new Adherent($this->zdb);

        if ($this->session->member !== null) {
            //retrieve from session, in add or edit
            $member = $this->session->member;
            $this->session->member = null;
            $id = $member->id;
        }
        $member->enableAllDeps();

        if ($id !== null) {
            //load requested member
            $member->load($id);
            $can = $member->canEdit($this->login);
        } else {
            $can = $member->canCreate($this->login);
        }

        if (!$can) {
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('me')
                );
        }

        //if adding a child, force parent here
        if ($action === 'addchild') {
            $member->setParent((int)$this->login->id);
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($id !== null) {
            $fc->setNotRequired('mdp_adh');
        }

        //handle requirements for parent fields
        $parent_fields = $member->getParentFields();
        $tpl_parent_fields = []; //for JS when detaching
        foreach ($parent_fields as $field) {
            if ($fc->isRequired($field)) {
                $tpl_parent_fields[] = $field;
                if ($member->hasParent()) {
                    $fc->setNotRequired($field);
                }
            }
        }

        // flagging required fields invisible to members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        // template variable declaration
        $title = _T("Member Profile");
        if ($member->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Status
        $statuts = new Status($this->zdb);
        //Titles
        $titles = new Titles($this->zdb);

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getSimpleList(true);

        $form_elements = $fc->getFormElements(
            $this->login,
            $id === null
        );

        // members
        $m = new Members();
        $pid = null;
        if ($member->hasParent()) {
            $pid = ($member->parent instanceof Adherent ? $member->parent->id : $member->parent);
        }
        $members = $m->getDropdownMembers(
            $this->zdb,
            $this->login,
            $pid
        );

        $route_params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $route_params['members']['list'] = $members;
        }

        if ($action === 'edit') {
            $route_params['navigate'] = $this->handleNavigationLinks($member->id);
        }

        // display page
        $this->view->render(
            $response,
            'pages/member_form.html.twig',
            array(
                'parent_tpl'        => 'page.html.twig',
                'autocomplete'      => true,
                'page_title'        => $title,
                'member'            => $member,
                'self_adh'          => false,
                // pseudo random int
                'time'              => time(),
                'titles_list'       => $titles->getList(),
                'statuts'           => $statuts->getList(),
                'groups'            => $groups_list,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens'],
                'parent_fields'     => $tpl_parent_fields,
                'addchild'          => ($action === 'addchild'),
                'osocials'          => new Social($this->zdb)
            ) + $route_params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Member id
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $this->store($request, $response);
    }

    /**
     * Massive change page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function massChange(Request $request, Response $response): Response
    {
        $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'masschange'])} ?? new MembersList();

        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->routeparser->urlFor('members')
        ];

        $fc = $this->fields_config;
        $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);

        //dynamic fields
        $member = new Adherent($this->zdb);
        $member->disableAllDeps()->enableDep('dynamics');

        //Status
        $statuts = new Status($this->zdb);
        //Titles
        $titles = new Titles($this->zdb);

        // display page
        $this->view->render(
            $response,
            'modals/mass_change_members.html.twig',
            array(
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    (string)count($data['id']),
                    _T('Mass change %count members')
                ),
                'form_url'      => $this->routeparser->urlFor('masschangeMembersReview'),
                'cancel_uri'    => $this->routeparser->urlFor('members'),
                'data'          => $data,
                'member'        => $member,
                'fieldsets'     => $form_elements['fieldsets'],
                'titles_list'   => $titles->getList(),
                'statuts'       => $statuts->getList(),
                'require_mass'  => true
            )
        );
        return $response;
    }

    /**
     * Massive changes validation page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function validateMassChange(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $changes = [];

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Mass changes has not been confirmed!")
            );
        } else {
            //we want only visibles fields
            $fc = $this->fields_config;
            $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);

            foreach ($form_elements['fieldsets'] as $form_element) {
                foreach ($form_element->elements as $field) {
                    if (
                        isset($post['mass_' . $field->field_id])
                        && (isset($post[$field->field_id]) || $field->type === FieldsConfig::TYPE_BOOL)
                    ) {
                        $changes[$field->field_id] = [
                            'label' => $field->label,
                            'value' => $post[$field->field_id] ?? 0
                        ];
                    }
                }
            }

            //handle dynamic fields
            $member = new Adherent($this->zdb);
            $member
                ->enableAllDeps()
                ->setDependencies(
                    $this->preferences,
                    $this->members_fields,
                    $this->history
                );
            $dynamic_fields = $member->getDynamicFields()->getFields();
            foreach ($dynamic_fields as $field) {
                $mass_id = 'mass_info_field_' . $field->getId();
                $field_id = 'info_field_' . $field->getId() . '_1';
                if (
                    isset($post[$mass_id])
                    && (isset($post[$field_id]) || $field instanceof Boolean)
                ) {
                    $changes[$field_id] = [
                        'label' => $field->getName(),
                        'value' => $post[$field_id] ?? 0
                    ];
                }
            }
        }

        $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'masschange'])};
        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->routeparser->urlFor('members')
        ];

        //Status
        $statuts = new Status($this->zdb);
        //Titles
        $titles = new Titles($this->zdb);

        // display page
        $this->view->render(
            $response,
            'modals/mass_change_members.html.twig',
            array(
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    (string)count($data['id']),
                    _T('Review mass change %count members')
                ),
                'form_url'      => $this->routeparser->urlFor('massstoremembers'),
                'cancel_uri'    => $this->routeparser->urlFor('members'),
                'data'          => $data,
                'titles_list'   => $titles->getList(),
                'statuts'       => $statuts->getList(),
                'changes'       => $changes
            )
        );
        return $response;
    }

    /**
     * Do massive changes
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doMassChange(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $redirect_url = $post['redirect_uri'];
        $error_detected = [];
        $mass = 0;
        $dynamic_fields = null;

        unset($post['redirect_uri']);
        if (!isset($post['confirm'])) {
            $error_detected[] = _T("Mass changes has not been confirmed!");
        } else {
            unset($post['confirm']);
            $ids = $post['id'];
            unset($post['id']);

            $fc = $this->fields_config;
            $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);
            $disabled = $this->members_fields;
            foreach (array_keys($post) as $key) {
                $found = false;
                foreach ($form_elements['fieldsets'] as $fieldset) {
                    if (isset($fieldset->elements[$key])) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    //try on dynamic fields
                    if ($dynamic_fields === null) {
                        //handle dynamic fields
                        $member = new Adherent($this->zdb);
                        $member
                            ->enableAllDeps()
                            ->setDependencies(
                                $this->preferences,
                                $this->members_fields,
                                $this->history
                            );
                        $dynamic_fields = $member->getDynamicFields()->getFields();
                    }
                    foreach ($dynamic_fields as $field) {
                        $field_id = 'info_field_' . $field->getId() . '_1';
                        if ($key == $field_id) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    Analog::log(
                        'Permission issue mass editing field ' . $key,
                        Analog::WARNING
                    );
                    unset($post[$key]);
                } else {
                    unset($disabled[$key]);
                }
            }

            if (!count($post)) {
                $error_detected[] = _T("Nothing to do!");
            } else {
                foreach ($ids as $id) {
                    $is_manager = !$this->login->isAdmin()
                        && !$this->login->isStaff()
                        && $this->login->isGroupManager();
                    $member = new Adherent($this->zdb);
                    $member
                        ->disableAllDeps()
                        ->disableEvents();
                    if ($is_manager) {
                        $member->enableDep('groups');
                    }
                    $member->load((int)$id);
                    $member->setDependencies(
                        $this->preferences,
                        $this->members_fields,
                        $this->history
                    );
                    if (!$member->canEdit($this->login)) {
                        continue;
                    }

                    $valid = $member->check($post, [], $disabled);
                    if ($valid === true) {
                        $done = $member->store();
                        if (!$done) {
                            $error_detected[] = _T("An error occurred while storing the member.");
                        } else {
                            ++$mass;
                        }
                    } else {
                        $error_detected = array_merge($error_detected, $valid);
                    }
                }
            }
        }

        if ($mass == 0 && !count($error_detected)) {
            $error_detected[] = _T('Something went wront during mass edition!');
        } else {
            $this->flash->addMessage(
                'success_detected',
                sprintf(
                    //TRANS: first parameter is the number of edited members
                    _Tn(
                        '%1$s member has been changed successfully!',
                        '%1$s members has been changed successfully!',
                        $mass
                    ),
                    $mass
                )
            );
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        if (!($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest')) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        } else {
            return $this->withJson(
                $response,
                [
                    'success' => count($error_detected) === 0
                ]
            );
        }
    }

    /**
     * Store
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function store(Request $request, Response $response): Response
    {
        if (!$this->preferences->pref_bool_selfsubscribe && !$this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('slash'));
        }

        $post = $request->getParsedBody();
        $member = new Adherent($this->zdb);
        $member
            ->enableAllDeps()
            ->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history
            );

        $success_detected = [];
        $error_detected = [];

        if ($this->isSelfMembership() && !isset($post[Adherent::PK])) {
            //mark as self membership
            $member->setSelfMembership();

            //check captcha
            /** @var Gaptcha $gaptcha */
            $gaptcha = $this->session->gaptcha;
            if (!$gaptcha->check((int)$post['gaptcha'])) {
                $error_detected[] = _T('Invalid captcha');
            }
        }

        // new or edit
        if (isset($post['id_adh'])) {
            $member->load((int)$post['id_adh']);
            if (!$member->canEdit($this->login)) {
                //redirection should have been done before. Just throw an Exception.
                throw new \RuntimeException(
                    str_replace(
                        '%id',
                        (string)$member->id,
                        'No right to store member #%id'
                    )
                );
            }
        } else {
            if ($member->id != '') {
                $member->load($this->login->id);
            }
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member but not from self subscription
        if ($member->id != '' || $this->isSelfMembership()) {
            $fc->setNotRequired('mdp_adh');
        }

        if (
            $member->hasParent() && !isset($post['detach_parent'])
            || isset($post['parent_id']) && !empty($post['parent_id'])
        ) {
            $parent_fields = $member->getParentFields();
            foreach ($parent_fields as $field) {
                if ($fc->isRequired($field)) {
                    $fc->setNotRequired($field);
                }
            }
        }

        // flagging required fields invisible to members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        $form_elements = $fc->getFormElements(
            $this->login,
            $member->id == '',
            $this->isSelfMembership()
        );
        $fieldsets     = $form_elements['fieldsets'];
        $required      = array();
        $disabled      = array();

        foreach ($fieldsets as $category) {
            foreach ($category->elements as $field) {
                if ($field->required) {
                    $required[$field->field_id] = true;
                }
                if ($field->disabled) {
                    $disabled[] = $field->field_id;
                } elseif (!isset($post[$field->field_id])) {
                    switch ($field->field_id) {
                        //unchecked booleans are not sent from form
                        case 'bool_admin_adh':
                        case 'bool_exempt_adh':
                        case 'bool_display_info':
                            $post[$field->field_id] = false;
                            break;
                    }
                }
            }
        }

        $real_requireds = array_diff(array_keys($required), array_values($disabled));

        // send email to member
        if ($this->isSelfMembership() || isset($post['mail_confirm']) && $post['mail_confirm'] == '1') {
            $member->setSendmail(); //flag to send creation email
        }

        // Validation
        $redirect_url = $this->routeparser->urlFor('member', ['id' => (string)$member->id]);
        if (!count($real_requireds) || isset($post[array_shift($real_requireds)])) {
            // regular fields
            $valid = $member->check($post, $required, $disabled);
            if ($valid !== true) {
                $error_detected = array_merge($error_detected, $valid);
            }

            if (count($error_detected) == 0) {
                //all goes well, we can proceed

                $new = false;
                if ($member->id == '') {
                    $new = true;
                }

                $store = $member->store();
                if ($store === true) {
                    //member has been stored :)
                    if ($new) {
                        if ($this->isSelfMembership()) {
                            $success_detected[] = _T("Your account has been created!");
                            if (
                                $this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                                && $member->getEmail() != ''
                            ) {
                                $success_detected[] = _T("An email has been sent to you, check your inbox.");
                            }
                        } else {
                            $success_detected[] = _T("New member has been successfully added.");
                        }
                    } else {
                        $success_detected[] = _T("Member account has been modified.");
                    }

                    if ($this->login->isGroupManager()) {
                        //add/remove user from groups
                        $groups_adh = $post['groups_adh'] ?? [];
                        $add_groups = Groups::addMemberToGroups(
                            $member,
                            $groups_adh
                        );

                        if ($add_groups === false) {
                            $error_detected[] = _T("An error occurred adding member to its groups.");
                        }
                    }
                    if ($this->login->isSuperAdmin() || $this->login->isAdmin() || $this->login->isStaff()) {
                        //add/remove manager from groups
                        $managed_groups_adh = $post['groups_managed_adh'] ?? [];
                        $add_groups = Groups::addMemberToGroups(
                            $member,
                            $managed_groups_adh,
                            true
                        );
                        $member->loadGroups();

                        if ($add_groups === false) {
                            $error_detected[] = _T("An error occurred adding member to its groups as manager.");
                        }
                    }
                } else {
                    //something went wrong :'(
                    $error_detected[] = _T("An error occurred while storing the member.");
                }
            }

            if (count($error_detected) === 0) {
                $cropping = null;
                if ($this->preferences->pref_force_picture_ratio == 1) {
                    $cropping = [];
                    $cropping['ratio'] = isset($this->preferences->pref_member_picture_ratio) ? $this->preferences->pref_member_picture_ratio : 'square_ratio';
                    $cropping['focus'] = isset($post['crop_focus']) ? $post['crop_focus'] : 'center';
                }
                $files_res = $member->handleFiles($_FILES, $cropping);
                if (is_array($files_res)) {
                    $error_detected = array_merge($error_detected, $files_res);
                }

                if (isset($post['del_photo'])) {
                    if (!$member->picture->delete()) {
                        $error_detected[] = _T("Delete failed");
                        $str_adh = $member->id . ' (' . $member->sname . ' ' . ')';
                        Analog::log(
                            'Unable to delete picture for member ' . $str_adh,
                            Analog::ERROR
                        );
                    }
                }
            }

            if (count($error_detected) > 0) {
                foreach ($error_detected as $error) {
                    if (strpos($error, '%member_url_') !== false) {
                        preg_match('/%member_url_(\d+)/', $error, $matches);
                        $url = $this->routeparser->urlFor('member', ['id' => $matches[1]]);
                        $error = str_replace(
                            '%member_url_' . $matches[1],
                            $url,
                            $error
                        );
                    }
                    $this->flash->addMessage(
                        'error_detected',
                        $error
                    );
                }
            }

            if (count($success_detected) > 0) {
                foreach ($success_detected as $success) {
                    $this->flash->addMessage(
                        'success_detected',
                        $success
                    );
                }
            }

            if (count($error_detected) === 0) {
                if ($this->isSelfMembership()) {
                    $redirect_url = $this->routeparser->urlFor('login');
                } elseif (
                    isset($post['redirect_on_create'])
                    && $post['redirect_on_create'] > Adherent::AFTER_ADD_DEFAULT
                ) {
                    switch ($post['redirect_on_create']) {
                        case Adherent::AFTER_ADD_TRANS:
                            $redirect_url = $this->routeparser->urlFor('addTransaction');
                            break;
                        case Adherent::AFTER_ADD_NEW:
                            $redirect_url = $this->routeparser->urlFor('addMember');
                            break;
                        case Adherent::AFTER_ADD_SHOW:
                            $redirect_url = $this->routeparser->urlFor('member', ['id' => (string)$member->id]);
                            break;
                        case Adherent::AFTER_ADD_LIST:
                            $redirect_url = $this->routeparser->urlFor('members');
                            break;
                        case Adherent::AFTER_ADD_HOME:
                            $redirect_url = $this->routeparser->urlFor('slash');
                            break;
                    }
                } elseif (!isset($post['id_adh']) && !$member->isDueFree()) {
                    $redirect_url = $this->routeparser->urlFor(
                        'addContribution',
                        ['type' => 'fee']
                    ) . '?id_adh=' . $member->id;
                } else {
                    $redirect_url = $this->routeparser->urlFor('member', ['id' => (string)$member->id]);
                }
            } else {
                //store entity in session
                $this->session->member = $member;

                if ($this->isSelfMembership()) {
                    $redirect_url = $this->routeparser->urlFor('subscribe');
                } else {
                    if ($member->id) {
                        $redirect_url = $this->routeparser->urlFor(
                            'editMember',
                            ['id'    => (string)$member->id]
                        );
                    } else {
                        $redirect_url = $this->routeparser->urlFor((isset($post['addchild']) ? 'addMemberChild' : 'addMember'));
                    }
                }
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }


    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('members');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveMember',
            $args
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args): string
    {
        if (isset($args['id_adh']) || isset($args['id'])) {
            //one member removal
            $id_adh = $args['id_adh'] ?? $args['id'];
            $adh = new Adherent($this->zdb, (int)$id_adh);
            return sprintf(
                _T('Remove member %1$s'),
                $adh->sfullname
            );
        } else {
            //batch members removal
            $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'delete'])};
            $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'delete'])} = $filters;
            return str_replace(
                '%count',
                (string)count($filters->selected),
                _T('You are about to remove %count members.')
            );
        }
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     *
     * @return bool
     */
    protected function doDelete(array $args, array $post): bool
    {
        if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'delete'])})) {
            $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'delete'])};
        } else {
            $filters = new MembersList();
        }
        $members = new Members($filters);

        if (!is_array($post['id'])) {
            $ids = (array)$post['id'];
        } else {
            $ids = $post['id'];
        }

        return $members->removeMembers($ids);
    }

    // CRUD - Delete

    /**
     * Set self memebrship flag
     *
     * @return MembersController
     */
    private function setSelfMembership(): MembersController
    {
        $this->is_self_membership = true;
        return $this;
    }

    /**
     * Is self membership?
     *
     * @return bool
     */
    private function isSelfMembership(): bool
    {
        return $this->is_self_membership;
    }

    /**
     * Handle navigation links
     *
     * @param int $id_adh Current member ID
     *
     * @return array<string,int>
     */
    private function handleNavigationLinks(int $id_adh): array
    {
        $navigate = array();

        if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName())})) {
            $filters = clone $this->session->{$this->getFilterName($this->getDefaultFilterName())};
        } else {
            $filters = new MembersList();
        }
        //we must navigate between all members
        $filters->show = 0;

        if (
            $this->login->isAdmin()
            || $this->login->isStaff()
            || $this->login->isGroupManager()
        ) {
            $m = new Members($filters);

            $ids = array();
            $fields = [Adherent::PK, 'nom_adh', 'prenom_adh'];
            if ($this->login->isAdmin() || $this->login->isStaff()) {
                $ids = $m->getMembersList(false, $fields);
            } else {
                $ids = $m->getManagedMembersList(false, $fields);
            }

            $ids = $ids->toArray();
            foreach ($ids as $k => $m) {
                if ($m['id_adh'] == $id_adh) {
                    $navigate = array(
                        'cur'  => $m['id_adh'],
                        'count' => $filters->counter,
                        'pos' => $k + 1
                    );
                    if ($k > 0) {
                        $navigate['prev'] = $ids[$k - 1]['id_adh'];
                    }
                    if ($k < count($ids) - 1) {
                        $navigate['next'] = $ids[$k + 1]['id_adh'];
                    }
                    break;
                }
            }
        }

        return $navigate;
    }

    /**
     * Get default filter name
     *
     * @return string
     */
    public static function getDefaultFilterName(): string
    {
        return 'members';
    }
}
