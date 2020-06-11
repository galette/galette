<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette members controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2020 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\Authentication;
use Galette\Core\GaletteMail;
use Galette\Core\Password;
use Galette\Core\PasswordImage;
use Galette\Core\Picture;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Entity\Group;
use Galette\Entity\Status;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Texts;
use Galette\Filters\AdvancedMembersList;
use Galette\Filters\MembersList;
use Galette\IO\File;
use Galette\IO\MembersCsv;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\Repository\PaymentTypes;
use Galette\Repository\Titles;
use Analog\Analog;

/**
 * Galette members controller
 *
 * @category  Controllers
 * @name      GaletteController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

class MembersController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function add(Request $request, Response $response, array $args = []): Response
    {
        return $this->edit($request, $response, $args);
    }

    /**
     * Self subscription page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function selfSubscribe(Request $request, Response $response, array $args = []): Response
    {
        if (!$this->preferences->pref_bool_selfsubscribe || $this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $deps = [
                'dynamics'  => true
            ];
            $member = new Adherent($this->zdb, null, $deps);
        }

        //mark as self membership
        $member->setSelfMembership();

        // flagging required fields
        $fc = $this->fields_config;
        $form_elements = $fc->getFormElements($this->login, true, true);

        //image to defeat mass filling forms
        $spam = new PasswordImage();
        $spam_pass = $spam->newImage();
        $spam_img = $spam->getImage();

        // members
        $m = new Members();
        $members = $m->getSelectizedMembers(
            $this->zdb,
            $member->hasParent() ? $member->parent->id : null
        );

        $params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            'member.tpl',
            array(
                'page_title'        => _T("Subscription"),
                'parent_tpl'        => 'public_page.tpl',
                'member'            => $member,
                'self_adh'          => true,
                'autocomplete'      => true,
                // pseudo random int
                'time'              => time(),
                'titles_list'       => Titles::getList($this->zdb),
                //self_adh specific
                'spam_pass'         => $spam_pass,
                'spam_img'          => $spam_img,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens']
            ) + $params
        );
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, array $args = []): Response
    {
        return $this->store($request, $response, $args);
    }

    /**
     * Duplicate action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function duplicate(Request $request, Response $response, array $args = []): Response
    {
        $id_adh = (int)$args[Adherent::PK];
        $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true, 'parent' => true]);
        $adh->setDuplicate();

        //store entity in session
        $this->session->member = $adh;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('editmember', ['action' => 'add']));
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Display member card
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true
        );
        $member = new Adherent($this->zdb, $id, $deps);

        if (!$member->canEdit($this->login)) {
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor('me')
                );
        }

        if ($member->id == null) {
            //member does not exists!
            $this->flash->addMessage(
                'error_detected',
                str_replace('%id', $args['id'], _T("No member #%id."))
            );

            return $response
                ->withStatus(404)
                ->withHeader(
                    'Location',
                    $this->router->pathFor('slash')
                );
        }

        // flagging fields visibility
        $fc = $this->fields_config;
        $display_elements = $fc->getDisplayElements($this->login);

        // display page
        $this->view->render(
            $response,
            'voir_adherent.tpl',
            array(
                'page_title'        => _T("Member Profile"),
                'member'            => $member,
                'pref_lang'         => $this->i18n->getNameFromId($member->language),
                'pref_card_self'    => $this->preferences->pref_card_self,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'display_elements'  => $display_elements
            )
        );
        return $response;
    }

    /**
     * Own card show
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function showMe(Request $request, Response $response, array $args = []): Response
    {
        if ($this->login->isSuperAdmin()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }
        $args['show_me'] = true;
        $args['id'] = $this->login->id;
        return $this->show($request, $response, $args);
    }

    /**
     * Public pages (trombinoscope, public list)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function publicList(Request $request, Response $response, array $args = []): Response
    {
        $option = null;
        $type = $args['type'];
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        $varname = 'public_filter_' . $type;
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
        $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);

        // display page
        $this->view->render(
            $response,
            ($type === 'list' ? 'liste_membres' : 'trombinoscope') . '.tpl',
            array(
                'page_title'    => ($type === 'list' ? _T("Members list") : _T('Trombinoscope')),
                'additionnal_html_class'    => ($type === 'list' ? '' : 'trombinoscope'),
                'type'          => $type,
                'members'       => $members,
                'nb_members'    => $m->getCount(),
                'filters'       => $filters
            )
        );
        return $response;
    }

    /**
     * Public pages (trombinoscope, public list)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function filterPublicList(Request $request, Response $response, array $args = []): Response
    {
        $type = $args['type'];
        $post = $request->getParsedBody();

        $varname = 'public_filter_' . $type;
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
                $filters->show = $post['nbshow'];
            }
        }

        $this->session->$varname = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('publicList', ['type' => $type]));
    }

    /**
     * Get a dynamic file
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function getDynamicFile(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => true
        );
        $member = new Adherent($this->zdb, $id, $deps);

        $denied = null;
        if (!$member->canEdit($this->login)) {
            $fields = $member->getDynamicFields()->getFields();
            if (!isset($fields[$args['fid']])) {
                //field does not exists or access is forbidden
                $denied = true;
            } else {
                $denied = false;
            }
        }

        if ($denied === true) {
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            return $response
                ->withStatus(403)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'member',
                        ['id' => $id]
                    )
                );
        }

        $filename = str_replace(
            [
                '%mid',
                '%fid',
                '%pos'
            ],
            [
                $args['id'],
                $args['fid'],
                $args['pos']
            ],
            'member_%mid_field_%fid_value_%pos'
        );

        if (file_exists(GALETTE_FILES_PATH . $filename)) {
            $type = File::getMimeType(GALETTE_FILES_PATH . $filename);
            $response = $response
                ->withHeader('Content-Type', $type)
                ->withHeader('Content-Disposition', 'attachment;filename="' . $args['name'] . '"')
                ->withHeader('Pragma', 'no-cache');
            $response->write(readfile(GALETTE_FILES_PATH . $filename));
            return $response;
        } else {
            Analog::log(
                'A request has been made to get an exported file named `' .
                $filename . '` that does not exists.',
                Analog::WARNING
            );

            $this->flash->addMessage(
                'error_detected',
                _T("The file does not exists or cannot be read :(")
            );

            return $response
                ->withStatus(404)
                ->withHeader(
                    'Location',
                    $this->router->pathFor('member', ['id' => $args['id']])
                );
        }
    }

    /**
     * Members list
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function list(Request $request, Response $response, array $args = []): Response
    {
        $option = $args['option'] ?? null;
        $value = $args['value'] ?? null;

        if (isset($this->session->filter_members)) {
            $filters = $this->session->filter_members;
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
        $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);
        $filters->setViewCommonsFilters($this->preferences, $this->view->getSmarty());

        $this->session->filter_members = $filters;

        // display page
        $this->view->render(
            $response,
            'gestion_adherents.tpl',
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
        if (isset($this->session->filter_members)) {
            //CAUTION: this one may be simple or advanced, display must change
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters = new MembersList();
        } elseif (isset($post['clear_adv_filter'])) {
            $this->session->filter_members = null;
            unset($this->session->filter_members);

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('advanced-search'));
        } elseif (isset($post['adv_criteria'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('advanced-search'));
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
            if (
                isset($post['group_filter'])
                && $post['group_filter'] > 0
            ) {
                $filters->group_filter = (int)$post['group_filter'];
            }
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
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
                                    $fs_search = $post['free_text'][$i];
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
                    $this->router->pathFor(
                        'saveSearch',
                        $post
                    )
                );
        }

        $this->session->filter_members = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('members'));
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
        if (isset($this->session->filter_members)) {
            $filters = $this->session->filter_members;
            if (!$filters instanceof AdvancedMembersList) {
                $filters = new AdvancedMembersList($filters);
            }
        } else {
            $filters = new AdvancedMembersList();
        }

        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        //we want only visibles fields
        $fields = $this->members_fields;
        $fc = $this->fields_config;
        $visibles = $fc->getVisibilities();
        $access_level = $this->login->getAccessLevel();

        //remove not searchable fields
        unset($fields['mdp_adh']);

        foreach ($fields as $k => $f) {
            if (
                $visibles[$k] == FieldsConfig::NOBODY ||
                ($visibles[$k] == FieldsConfig::ADMIN &&
                    $access_level < Authentication::ACCESS_ADMIN) ||
                ($visibles[$k] == FieldsConfig::STAFF &&
                    $access_level < Authentication::ACCESS_STAFF) ||
                ($visibles[$k] == FieldsConfig::MANAGER &&
                    $access_level < Authentication::ACCESS_MANAGER)
            ) {
                unset($fields[$k]);
            }
        }

        //add status label search
        if ($pos = array_search(Status::PK, array_keys($fields))) {
            $fields = array_slice($fields, 0, $pos, true) +
                ['status_label'  => ['label' => _T('Status label')]] +
                array_slice($fields, $pos, count($fields) - 1, true);
        }

        //dynamic fields
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false
        );
        $member = new Adherent($this->zdb, $this->login->login, $deps);
        $adh_dynamics = new DynamicFieldsHandle($this->zdb, $this->login, $member);

        $contrib = new Contribution($this->zdb, $this->login);
        $contrib_dynamics = new DynamicFieldsHandle($this->zdb, $this->login, $contrib);

        //Status
        $statuts = new Status($this->zdb);

        //Contributions types
        $ct = new ContributionsTypes($this->zdb);

        //Payments types
        $ptypes = new PaymentTypes(
            $this->zdb,
            $this->preferences,
            $this->login
        );
        $ptlist = $ptypes->getList();

        $filters->setViewCommonsFilters($this->preferences, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'advanced_search.tpl',
            array(
                'page_title'            => _T("Advanced search"),
                'filter_groups_options' => $groups_list,
                'search_fields'         => $fields,
                'adh_dynamics'          => $adh_dynamics->getFields(),
                'contrib_dynamics'      => $contrib_dynamics->getFields(),
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
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function ajaxList(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();

        if (isset($this->session->ajax_members_filters)) {
            $filters = $this->session->ajax_members_filters;
        } else {
            $filters = new MembersList();
        }

        if (isset($args['option']) && $args['option'] == 'page') {
            $filters->current_page = (int)$args['value'];
        }

        //numbers of rows to display
        if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
            $filters->show = $post['nbshow'];
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
        $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);

        $this->session->ajax_members_filters = $filters;

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
                        exit(0);
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
                            exit(0);
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
                        exit(0);
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
            $params['the_id'] = $post['gid'];
        }

        if (isset($post['id_adh'])) {
            $params['excluded'] = $post['id_adh'];
        }

        // display page
        $this->view->render(
            $response,
            'ajax_members.tpl',
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

        if (isset($post['member_sel'])) {
            if (isset($this->session->filter_members)) {
                $filters = $this->session->filter_members;
            } else {
                $filters = new MembersList();
            }

            $filters->selected = $post['member_sel'];
            $this->session->filter_members = $filters;

            if (isset($post['cards'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('pdf-members-cards'));
            }

            if (isset($post['labels'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('pdf-members-labels'));
            }

            if (isset($post['mailing'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('mailing') . '?mailing_new=new');
            }

            if (isset($post['attendance_sheet'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('attendance_sheet_details'));
            }

            if (isset($post['csv'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('csv-memberslist'));
            }

            if (isset($post['delete'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('removeMembers'));
            }

            if (isset($post['masschange'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('masschangeMembers'));
            }

            throw new \RuntimeException('Does not know what to batch :(');
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No member was selected, please check at least one name.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $action = $args['action'];
        $id = null;
        if (isset($args['id'])) {
            $id = (int)$args['id'];
        }

        if ($action === 'edit' && $id === null) {
            throw new \RuntimeException(
                _T("Member ID cannot ben null calling edit route!")
            );
        } elseif ($action === 'add' && $id !== null) {
             return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('editmember', ['action' => 'add']));
        }
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true
        );

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $member = new Adherent($this->zdb, $id, $deps);
        }

        if ($id !== null) {
            $member->load($id);
            if (!$member->canEdit($this->login)) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("You do not have permission for requested URL.")
                );

                return $response
                    ->withStatus(403)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor('me')
                    );
            }
        } else {
            if ($member->id != $id) {
                $member->load($this->login->id);
            }
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($member->id != '') {
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

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getSimpleList(true);

        $form_elements = $fc->getFormElements(
            $this->login,
            $member->id == ''
        );

        // members
        $m = new Members();
        $id = null;
        if ($member->hasParent()) {
            $id = ($member->parent instanceof Adherent ? $member->parent->id : $member->parent);
        }
        $members = $m->getSelectizedMembers(
            $this->zdb,
            $id
        );

        $route_params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $route_params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            'member.tpl',
            array(
                'parent_tpl'        => 'page.tpl',
                'autocomplete'      => true,
                'page_title'        => $title,
                'member'            => $member,
                'self_adh'          => false,
                // pseudo random int
                'time'              => time(),
                'titles_list'       => Titles::getList($this->zdb),
                'statuts'           => $statuts->getList(),
                'groups'            => $groups_list,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens'],
                'parent_fields'     => $tpl_parent_fields
            ) + $route_params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, array $args = []): Response
    {
        return $this->store($request, $response, $args);
    }

    /**
     * Massive change page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function massChange(Request $request, Response $response, array $args = []): Response
    {
        $filters = $this->session->filter_members;

        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->router->pathFor('members')
        ];

        $fc = $this->fields_config;
        $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);

        //dynamic fields
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false
        );
        $member = new Adherent($this->zdb, null, $deps);

        //Status
        $statuts = new Status($this->zdb);

        // display page
        $this->view->render(
            $response,
            'mass_change_members.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    count($data['id']),
                    _T('Mass change %count members')
                ),
                'form_url'      => $this->router->pathFor('masschangeMembersReview'),
                'cancel_uri'    => $this->router->pathFor('members'),
                'data'          => $data,
                'member'        => $member,
                'fieldsets'     => $form_elements['fieldsets'],
                'titles_list'   => Titles::getList($this->zdb),
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
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function validateMassChange(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Mass changes has not been confirmed!")
            );
        } else {
            //we want only visibles fields
            $fc = $this->fields_config;
            $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);

            $changes = [];
            foreach ($form_elements['fieldsets'] as $form_element) {
                foreach ($form_element->elements as $field) {
                    if (isset($post[$field->field_id]) && isset($post['mass_' . $field->field_id])) {
                        $changes[$field->field_id] = [
                            'label' => $field->label,
                            'value' => $post[$field->field_id]
                        ];
                    }
                }
            }
        }

        $filters = $this->session->filter_members;
        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->router->pathFor('members')
        ];

        //Status
        $statuts = new Status($this->zdb);

        // display page
        $this->view->render(
            $response,
            'mass_change_members.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    count($data['id']),
                    _T('Review mass change %count members')
                ),
                'form_url'      => $this->router->pathFor('massstoremembers'),
                'cancel_uri'    => $this->router->pathFor('members'),
                'data'          => $data,
                'titles_list'   => Titles::getList($this->zdb),
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
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doMassChange(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $redirect_url = $post['redirect_uri'];
        $error_detected = [];
        $mass = 0;

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
                        continue;
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
                    $deps = array(
                        'picture'   => false,
                        'groups'    => $is_manager,
                        'dues'      => false,
                        'parent'    => false,
                        'children'  => false,
                        'dynamics'  => false
                    );
                    $member = new Adherent($this->zdb, (int)$id, $deps);
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
                str_replace(
                    '%count',
                    $mass,
                    _T('%count members has been changed successfully!')
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

        if (!$request->isXhr()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        } else {
            return $response->withJson(
                [
                    'success'   => count($error_detected) === 0
                ]
            );
        }
    }

    /**
     * Store
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function store(Request $request, Response $response, array $args = []): Response
    {
        if (!$this->preferences->pref_bool_selfsubscribe && !$this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        $post = $request->getParsedBody();
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true
        );
        $member = new Adherent($this->zdb, null, $deps);
        $member->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        if (isset($args['self'])) {
            //mark as self membership
            $member->setSelfMembership();
        }

        $success_detected = [];
        $warning_detected = [];
        $error_detected = [];

        // new or edit
        $adherent['id_adh'] = get_numeric_form_value('id_adh', '');
        if ($this->login->isAdmin() || $this->login->isStaff() || $this->login->isGroupManager()) {
            if ($adherent['id_adh']) {
                $member->load((int)$adherent['id_adh']);
                if (!$member->canEdit($this->login)) {
                    //redirection should have been done before. Just throw an Exception.
                    throw new \RuntimeException(
                        str_replace(
                            '%id',
                            $member->id,
                            'No right to store member #%id'
                        )
                    );
                }
            }
        } else {
            $member->load($this->login->id);
            $adherent['id_adh'] = $this->login->id;
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($member->id != '') {
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
            isset($args['self'])
        );
        $fieldsets     = $form_elements['fieldsets'];
        $required      = array();
        $disabled      = array();

        foreach ($fieldsets as $category) {
            foreach ($category->elements as $field) {
                if ($field->required == true) {
                    $required[$field->field_id] = true;
                }
                if ($field->disabled == true) {
                    $disabled[$field->field_id] = true;
                } elseif (!isset($post[$field->field_id])) {
                    switch ($field->field_id) {
                        //unchecked booleans are not sent from form
                        case 'bool_admin_adh':
                        case 'bool_exempt_adh':
                        case 'bool_display_info':
                            $post[$field->field_id] = 0;
                            break;
                    }
                }
            }
        }

        $real_requireds = array_diff(array_keys($required), array_keys($disabled));

        // Validation
        $redirect_url = $this->router->pathFor('member', ['id' => $member->id]);
        if (isset($post[array_shift($real_requireds)])) {
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
                        if (isset($args['self'])) {
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
                        //Send email to admin if preference checked
                        if (
                            $this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                            && $this->preferences->pref_bool_mailadh
                        ) {
                            $texts = new Texts(
                                $this->preferences,
                                $this->router,
                                array(
                                    'name_adh'      => custom_html_entity_decode(
                                        $member->sname
                                    ),
                                    'firstname_adh' => custom_html_entity_decode(
                                        $member->surname
                                    ),
                                    'lastname_adh'  => custom_html_entity_decode(
                                        $member->name
                                    ),
                                    'mail_adh'      => custom_html_entity_decode(
                                        $member->email
                                    ),
                                    'login_adh'     => custom_html_entity_decode(
                                        $member->login
                                    )
                                )
                            );
                            $mtxt = $texts->getTexts(
                                (isset($args['self']) ? 'newselfadh' : 'newadh'),
                                $this->preferences->pref_lang
                            );

                            $mail = new GaletteMail($this->preferences);
                            $mail->setSubject($texts->getSubject());
                            $recipients = [];
                            foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                                $recipients[$pref_email] = $pref_email;
                            }
                            $mail->setRecipients($recipients);
                            $mail->setMessage($texts->getBody());
                            $sent = $mail->send();

                            if ($sent == GaletteMail::MAIL_SENT) {
                                $this->history->add(
                                    str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->email . ')',
                                        _T("New account email sent to admin for '%s'.")
                                    )
                                );
                            } else {
                                $str = str_replace(
                                    '%s',
                                    $member->sname . ' (' . $member->email . ')',
                                    _T("A problem happened while sending email to admin for account '%s'.")
                                );
                                $this->history->add($str);
                                $warning_detected[] = $str;
                            }
                            unset($texts);
                        }
                    } else {
                        $success_detected[] = _T("Member account has been modified.");
                    }

                    // send email to member
                    if (isset($args['self']) || isset($post['mail_confirm']) && $post['mail_confirm'] == '1') {
                        if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                            if ($member->getEmail() == '' && !isset($args['self'])) {
                                $error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
                            } else {
                                $mreplaces = [
                                    'name_adh'      => custom_html_entity_decode(
                                        $member->sname
                                    ),
                                    'firstname_adh' => custom_html_entity_decode(
                                        $member->surname
                                    ),
                                    'lastname_adh'  => custom_html_entity_decode(
                                        $member->name
                                    ),
                                    'mail_adh'      => custom_html_entity_decode(
                                        $member->getEmail()
                                    ),
                                    'login_adh'     => custom_html_entity_decode(
                                        $member->login
                                    )
                                ];
                                if ($new) {
                                    $password = new Password($this->zdb);
                                    $res = $password->generateNewPassword($member->id);
                                    if ($res == true) {
                                        $link_validity = new \DateTime();
                                        $link_validity->add(new \DateInterval('PT24H'));
                                        $mreplaces['change_pass_uri'] = $this->preferences->getURL() .
                                            $this->router->pathFor(
                                                'password-recovery',
                                                ['hash' => base64_encode($password->getHash())]
                                            );
                                        $mreplaces['link_validity'] = $link_validity->format(_T("Y-m-d H:i:s"));
                                    } else {
                                        $str = str_replace(
                                            '%s',
                                            $member->sfullname,
                                            _T("An error occurred storing temporary password for %s. Please inform an admin.")
                                        );
                                        $this->history->add($str);
                                        $this->flash->addMessage(
                                            'error_detected',
                                            $str
                                        );
                                    }
                                }

                                //send email to member
                                // Get email text in database
                                $texts = new Texts(
                                    $this->preferences,
                                    $this->router,
                                    $mreplaces
                                );
                                $mlang = $this->preferences->pref_lang;
                                if (isset($post['pref_lang'])) {
                                    $mlang = $post['pref_lang'];
                                }
                                $mtxt = $texts->getTexts(
                                    (($new) ? 'sub' : 'accountedited'),
                                    $mlang
                                );

                                $mail = new GaletteMail($this->preferences);
                                $mail->setSubject($texts->getSubject());
                                $mail->setRecipients(
                                    array(
                                        $member->getEmail() => $member->sname
                                    )
                                );
                                $mail->setMessage($texts->getBody());
                                $sent = $mail->send();

                                if ($sent == GaletteMail::MAIL_SENT) {
                                    $msg = str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->getEmail() . ')',
                                        ($new) ?
                                        _T("New account email sent to '%s'.") : _T("Account modification email sent to '%s'.")
                                    );
                                    $this->history->add($msg);
                                    $success_detected[] = $msg;
                                } else {
                                    $str = str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->getEmail() . ')',
                                        _T("A problem happened while sending account email to '%s'")
                                    );
                                    $this->history->add($str);
                                    $error_detected[] = $str;
                                }
                            }
                        } elseif ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                            //if email has been disabled in the preferences, we should not be here ;
                            //we do not throw an error, just a simple warning that will be show later
                            $msg = _T("You asked Galette to send a confirmation email to the member, but email has been disabled in the preferences.");
                            $warning_detected[] = $msg;
                        }
                    }

                    // send email to admin
                    if (
                        $this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                        && $this->preferences->pref_bool_mailadh
                        && !$new
                        && $member->id == $this->login->id
                    ) {
                        $mreplaces = [
                            'name_adh'      => custom_html_entity_decode(
                                $member->sname
                            ),
                            'firstname_adh' => custom_html_entity_decode(
                                $member->surname
                            ),
                            'lastname_adh'  => custom_html_entity_decode(
                                $member->name
                            ),
                            'mail_adh'      => custom_html_entity_decode(
                                $member->getEmail()
                            ),
                            'login_adh'     => custom_html_entity_decode(
                                $member->login
                            )
                        ];

                        //send email to member
                        // Get email text in database
                        $texts = new Texts(
                            $this->preferences,
                            $this->router,
                            $mreplaces
                        );
                        $mlang = $this->preferences->pref_lang;

                        $mtxt = $texts->getTexts(
                            'admaccountedited',
                            $mlang
                        );

                        $mail = new GaletteMail($this->preferences);
                        $mail->setSubject($texts->getSubject());
                        $recipients = [];
                        foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                            $recipients[$pref_email] = $pref_email;
                        }
                        $mail->setRecipients($recipients);

                        $mail->setMessage($texts->getBody());
                        $sent = $mail->send();

                        if ($sent == GaletteMail::MAIL_SENT) {
                            $msg = _T("Account modification email sent to admin.");
                            $this->history->add($msg);
                            $success_detected[] = $msg;
                        } else {
                            $str = _T("A problem happened while sending account email to admin");
                            $this->history->add($str);
                            $warning_detected[] = $str;
                        }
                    }

                    //store requested groups
                    $add_groups = null;
                    $groups_adh = null;
                    $managed_groups_adh = null;

                    //add/remove user from groups
                    if (isset($post['groups_adh'])) {
                        $groups_adh = $post['groups_adh'];
                    }
                    $add_groups = Groups::addMemberToGroups(
                        $member,
                        $groups_adh
                    );

                    if ($add_groups === false) {
                        $error_detected[] = _T("An error occurred adding member to its groups.");
                    }

                    //add/remove manager from groups
                    if (isset($post['groups_managed_adh'])) {
                        $managed_groups_adh = $post['groups_managed_adh'];
                    }
                    $add_groups = Groups::addMemberToGroups(
                        $member,
                        $managed_groups_adh,
                        true
                    );
                    $member->loadGroups();

                    if ($add_groups === false) {
                        $error_detected[] = _T("An error occurred adding member to its groups as manager.");
                    }
                } else {
                    //something went wrong :'(
                    $error_detected[] = _T("An error occurred while storing the member.");
                }
            }

            if (count($error_detected) == 0) {
                $files_res = $member->handleFiles($_FILES);
                if (is_array($files_res)) {
                    $error_detected = array_merge($error_detected, $files_res);
                }

                if (isset($post['del_photo'])) {
                    if (!$member->picture->delete($member->id)) {
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
                        $url = $this->router->pathFor('member', ['id' => $matches[1]]);
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

            if (count($warning_detected) > 0) {
                foreach ($warning_detected as $warning) {
                    $this->flash->addMessage(
                        'warning_detected',
                        $warning
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

            if (count($error_detected) == 0) {
                if (isset($args['self'])) {
                    $redirect_url = $this->router->pathFor('login');
                } elseif (
                    isset($post['redirect_on_create'])
                    && $post['redirect_on_create'] > Adherent::AFTER_ADD_DEFAULT
                ) {
                    switch ($post['redirect_on_create']) {
                        case Adherent::AFTER_ADD_TRANS:
                            $redirect_url = $this->router->pathFor('transaction', ['action' => 'add']);
                            break;
                        case Adherent::AFTER_ADD_NEW:
                            $redirect_url = $this->router->pathFor('editmember', ['action' => 'add']);
                            break;
                        case Adherent::AFTER_ADD_SHOW:
                            $redirect_url = $this->router->pathFor('member', ['id' => $member->id]);
                            break;
                        case Adherent::AFTER_ADD_LIST:
                            $redirect_url = $this->router->pathFor('members');
                            break;
                        case Adherent::AFTER_ADD_HOME:
                            $redirect_url = $this->router->pathFor('slash');
                            break;
                    }
                } elseif (!isset($post['id_adh']) && !$member->isDueFree()) {
                    $redirect_url = $this->router->pathFor(
                        'addContribution',
                        ['type' => 'fee']
                    ) . '?id_adh=' . $member->id;
                } else {
                    $redirect_url = $this->router->pathFor('member', ['id' => $member->id]);
                }
            } else {
                //store entity in session
                $this->session->member = $member;

                if (isset($args['self'])) {
                    $redirect_url = $this->router->pathFor('subscribe');
                } else {
                    if ($member->id) {
                        $rparams = [
                            'id'    => $member->id,
                            'action'    => 'edit'
                        ];
                    } else {
                        $rparams = ['action' => 'add'];
                    }
                    $redirect_url = $this->router->pathFor(
                        'editmember',
                        $rparams
                    );
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
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args = [])
    {
        return $this->router->pathFor('members');
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args = [])
    {
        return $this->router->pathFor(
            'doRemoveMember',
            $args
        );
    }


    /**
     * Get ID to remove
     *
     * In simple cases, we get the ID in the route arguments; but for
     * batchs, it should be found elsewhere.
     * In post values, we look for id key, as well as all {sthing}_sel keys (like members_sel or contrib_sel)
     *
     * @param array $args Request arguments
     * @param array $post POST values
     *
     * @return null|integer|integer[]
     */
    protected function getIdsToRemove(&$args, $post)
    {
        if (isset($args['id'])) {
            return $args['id'];
        } else {
            $filters = $this->session->filter_members;
            return $filters->selected;
        }
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args = [])
    {
        if (isset($args['id_adh'])) {
            //one member removal
            $adh = new Adherent($this->zdb, (int)$args['id_adh']);
            return sprintf(
                _T('Remove member %1$s'),
                $adh->sfullname
            );
        } else {
            //batch members removal
            $filters = $this->session->filter_members;
            return str_replace(
                '%count',
                count($filters->selected),
                _T('You are about to remove %count members.')
            );
        }
    }

    /**
     * Remove object
     *
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
    {
        if (isset($this->session->filter_members)) {
            $filters = $this->session->filter_members;
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
}
