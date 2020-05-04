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
use Galette\Core\Picture;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Entity\Status;
use Galette\Entity\FieldsConfig;
use Galette\Filters\AdvancedMembersList;
use Galette\Filters\MembersList;
use Galette\IO\MembersCsv;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\Repository\PaymentTypes;
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
    public function add(Request $request, Response $response, array $args = []) :Response
    {
        //TODO
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
    public function doAdd(Request $request, Response $response, array $args = []) :Response
    {
        //TODO
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
    public function duplicate(Request $request, Response $response, array $args = []) :Response
    {
        $id_adh = (int)$args[Adherent::PK];
        $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true]);
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
    public function show(Request $request, Response $response, array $args) :Response
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
                ->withStatus(403)
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
     * Members list
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function list(Request $request, Response $response, array $args = []) :Response
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
                'adv_filters'           => $filters instanceof AdvancedMembersList
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
    public function filter(Request $request, Response $response) :Response
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
            if (isset($post['group_filter'])
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
                                if (trim($f) !== ''
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
    public function advancedSearch(Request $request, Response $response) :Response
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
            if ($visibles[$k] == FieldsConfig::NOBODY ||
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
                array_slice($fields, $pos, count($fields) -1, true);
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
     * Batch actions handler
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function handleBatch(Request $request, Response $response) :Response
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
    public function edit(Request $request, Response $response, array $args = []) :Response
    {
        //TODO
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
    public function doEdit(Request $request, Response $response, array $args = []) :Response
    {
        //TODO
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
    public function store(Request $request, Response $response, array $args = []) :Response
    {
        //TODO
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
     *
     * @param array $args Request arguments
     *
     * @return integer|null|array
     */
    protected function getIdsToRemove($args)
    {
        if (isset($args['id'])) {
            return $args['id'];
        } else {
            $filters =  $this->session->filter_members;
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
            $adh = new Adherent($this->zdb, (int)$args['id']);
            return sprintf(
                _T('Remove member %1$s'),
                $adh->sfullname
            );
        } else {
            //batch members removal
            $filters =  $this->session->filter_members;
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
            $filters =  $this->session->filter_members;
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
