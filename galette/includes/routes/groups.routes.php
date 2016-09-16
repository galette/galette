<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups related routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-27
 */

use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\IO\PdfGroups;

$app->get(
    '/groups[/{id:\d+}]',
    function ($request, $response, $args) {
        $groups = new Groups($this->zdb, $this->login);
        $group = new Group();
        $group->setLogin($this->login);

        $groups_root = $groups->getList(false);
        $groups_list = $groups->getList();

        $id = null;
        if (isset($args['id'])) {
            $id = $args['id'];
            if ($this->login->isGroupManager($id)) {
                $group->load($id);
            } else {
                Analog::log(
                    'Trying to display group ' . $id . ' without appropriate permissions',
                    Analog::INFO
                );
                $response->setStatus(403);
                return $response;
            }
        }

        if ($id === null && count($groups_root) > 0) {
            reset($groups);
            $group = current($groups_root);
            if (!$this->login->isGroupManager($group->getId())) {
                foreach ($groups_list as $g) {
                    if ($this->login->isGroupManager($g->getId())) {
                        $group = $g;
                        break;
                    }
                }
            }
        }

        // display page
        $this->view->render(
            $response,
            'gestion_groupes.tpl',
            array(
                'page_title'            => _T("Groups"),
                'require_dialog'        => true,
                'require_tabs'          => true,
                'require_tree'          => true,
                'groups_root'           => $groups_root,
                'groups'                => $groups_list,
                'group'                 => $group
            )
        );
        return $response;
    }
)->setName('groups')->add($authenticate);

$app->get(
    '/group/add/{name}',
    function ($request, $response, $args) {
        $group = new Group();
        $group->setLogin($this->login);
        $group->setName($args['name']);
        if (!$this->login->isSuperAdmin()) {
            $group->setManagers(new Adherent($this->zdb, $this->login->id));
        }
        $group->store();
        $id = $group->getId();

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('groups', ["id" => $id]));
    }
)->setName('add_group')->add($authenticate);

$app->post(
    '/group/edit/{id:\d+}',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $group = new Group((int)$args['id']);
        $error = false;

        $group->setName($_POST['group_name']);
        try {
            if ($post['parent_group'] !== '') {
                $group->setParentGroup((int)$post['parent_group']);
            } else {
                $group->detach();
            }

            //handle group managers
            $managers_id = [];
            if (isset($post['managers'])) {
                $managers_id = $post['managers'];
            }
            $m = new Members();
            $managers = $m->getArrayList($managers_id);
            $group->setManagers($managers);

            //handle group members
            $members_id = [];
            if (isset($post['members'])) {
                $members_id = $post['members'];
            }
            $members = $m->getArrayList($members_id);
            $group->setMembers($members);

            $store = $group->store();
            if ($store === true) {
                $this->flash->addMessage(
                    'success_detected',
                    str_replace(
                        '%groupname',
                        $group->getName(),
                        _T("Group `%groupname` has been successfully saved.")
                    )
                );
            } else {
                //something went wrong :'(
                $this->flash->addMessage(
                    'error_detected',
                    _T("An error occured while storing the group.")
                );
            }
        } catch (Exception $e) {
            $this->flash->addMessage(
                'error_detected',
                $e->getMessage()
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('groups', ['id' => $group->getId()]));
    }
)->setName('doEditGroup')->add($authenticate);

$app->get(
    '/group/remove/{id:\d+}',
    function ($request, $response, $args) {
        $group = new Group((int)$args['id']);

        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('groups')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => _T("Group"),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T("Remove group %1\$s"),
                    $group->getFullName()
                ),
                'form_url'      => $this->router->pathFor('doRemoveGroup', ['id' => $group->getId()]),
                'cancel_uri'    => $this->router->pathFor('groups', ['id' => $group->getId()]),
                'data'          => $data,
                'with_cascade'  => 'true'
            )
        );
        return $response;
    }
)->setName('removeGroup')->add($authenticate);

$app->post(
    '/group/remove/{id:\d+}',
    function ($request, $response) {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = isset($post['redirect_uri']) ?
            $post['redirect_uri'] :
            $this->router->pathFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            //delete groups
            $group = new Group((int)$post['id']);
            $cascade = isset($post['cascade']);
            $del = $group->remove($cascade);
            if ($del !== true) {
                if ($group->isEmpty() === false) {
                    $error_detected = _T("Group is not empty, it cannot be deleted. Use cascade delete instead.");
                } else {
                    $error_detected = _T("An error occured trying to remove group :/");
                }

                $this->flash->addMessage(
                    'error_detected',
                    $error_detected
                );
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    str_replace(
                        '%groupname',
                        $group->getName(),
                        _T("Group %groupname has been successfully deleted.")
                    )
                );
                $success = true;
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $response->withJson(
                [
                    'success'   => $success
                ]
            );
        }
    }
)->setName('doRemoveGroup')->add($authenticate);

$app->get(
    '/pdf/groups[/{id:\d+}]',
    function ($request, $response, $args) {
        $groups = new Groups($this->zdb, $this->login);

        $groups_list = null;
        if (isset($args['id'])) {
            $groups_list = $groups->getList(true, $args['id']);
        } else {
            $groups_list = $groups->getList();
        }

        if (!is_array($groups_list) || count($groups_list) < 1) {
            Analog::log(
                'An error has occured, unable to get groups list.',
                Analog::ERROR
            );

            $this->flash->addMessage(
                'error_detected',
                _T("Unable to get groups list.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('groups'));
        }

        $pdf = new PdfGroups($this->preferences);
        $pdf->draw($groups_list, $this->login);
        $pdf->Output(_T("groups_list") . '.pdf', 'D');
    }
)->setName('pdf_groups')->add($authenticate);

$app->post(
    '/ajax/group',
    function ($request, $response) {
        $post = $request->getParsedBody();
        $id = $post['id_group'];
        $group = new Galette\Entity\Group((int)$id);

        $groups = new Groups($this->zdb, $this->login);

        // display page
        $this->view->render(
            $response,
            'group.tpl',
            array(
                'mode'      => 'ajax',
                'groups'    => $groups->getList(),
                'group'     => $group
            )
        );
        return $response;
    }
)->setName('ajax_group')->add($authenticate);

$app->post(
    '/ajax/unique_groupname',
    function ($request, $response) {
        $post = $request->getParsedBody();
        if (!isset($post['gname']) || $post['gname'] == '') {
            Analog::log(
                'Trying to check if group name is unique without name specified',
                Analog::INFO
            );
            echo json_encode(['success' => false, 'message' => htmlentities(_T("Group name is missing!"))]);
        } else {
            echo json_encode(['success' => Groups::isUnique($this->zdb, $post['gname'])]);
        }
    }
)->setName('ajax_groupname_unique')->add($authenticate);
