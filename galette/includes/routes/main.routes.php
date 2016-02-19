<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main routes
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
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Core\Picture;
use Galette\Core\SysInfos;
use Galette\Entity\Adherent;

//main route
$app->get(
    '/',
    function ($request, $response, $args) use ($baseRedirect) {
        return $baseRedirect($request, $response, $args);
    }
)->setName('slash');

//logo route
$app->get(
    '/logo',
    function ($request, $response, $args) {
        $this->logo->display();
    }
)->setName('logo');

//photo route
$app->get(
    '/photo/{id:\d+}',
    function ($request, $response, $args) {
        $id = $args['id'];
        /** FIXME: we load entire member here... No need to do so! */
        $deps = array(
            'groups'    => false,
            'dues'      => false
        );
        $adh = new Adherent((int)$id, $deps);

        $picture = null;
        if ($this->login->isAdmin()
            || $this->login->isStaff()
            || $adh->appearsInMembersList()
            || $this->login->login == $adh->login
        ) {
            $picture = $adh->picture;
        } else {
            $picture = new Picture();
        }
        $picture->display();
    }
)->setName('photo');

//system informations
$app->get(
    '/sysinfos',
    function ($request, $response, $args = []) {
        $sysinfos = new SysInfos();
        $sysinfos->grab();

        // display page
        $this->view->render(
            $response,
            'sysinfos.tpl',
            array(
                'page_title'    => _T("System informations"),
                'rawinfos'      => $sysinfos->getRawData($this->plugins)
            )
        );
        return $response;
    }
)->setName('sysinfos')->add($authenticate);

//impersonating
$app->get(
    '/impersonate/{id:\d+}',
    function ($request, $response, $args) {
        $success = $login->impersonate($id);

        if ($success === true) {
            $this->session->login = $login;
            $this->login = $login;
            $msg = str_replace(
                '%login',
                $login->login,
                _T("Impersonating as %login")
            );

            $this->history->add($msg);
            $this->flash->addMessage(
                'success_detected',
                [$msg]
            );
        } else {
            $msg = str_replace(
                '%id',
                $id,
                _T("Unable to impersonate as %id")
            );
            $this->flash->addMessage(
                'error_detected',
                $msg
            );
            $this->history->add($msg);
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }
)->setName('impersonate')->add($authenticate);

$app->get(
    '/unimpersonate',
    function ($request, $response, $args) {
        $login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
        $login->logAdmin($this->preferences->pref_admin_login, $this->preferences);
        $this->history->add(_T("Impersonating ended"));
        $this->session->login = $login;
        $this->login = $login;
        $this->flash->addMessage(
            'success_detected',
            [_T("Impersonating ended")]
        );
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }
)->setName('unimpersonate')->add($authenticate);
