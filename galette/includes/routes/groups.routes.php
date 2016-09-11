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

use Galette\Entity\Group;
use Galette\Repository\Groups;
use Galette\IO\PdfGroups;

$app->get(
    '/groups[/{id}]',
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
    '/pdf/groups[/{id}]',
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
