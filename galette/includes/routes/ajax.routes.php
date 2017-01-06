<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Ajax routes
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

$app->group(__('/ajax', 'routes'), function () {
    $this->get(
        __('/messages', 'routes'),
        function ($request, $response) {
            $this->view->render(
                $response,
                'ajax_messages.tpl'
            );
            return $response;
        }
    )->setName('ajaxMessages');

    $this->post(
        __('photo', 'routes'),
        function ($request, $response) {
            $post = $request->getParsedBody();
            $ret = ['result' => false];

            if (!isset($post['member_id'])
                || !isset($post['file'])
                || !isset($post['filename'])
                || !isset($post['filesize'])
            ) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Required argument not present!")
                );
                return $response->withJson($ret);
            }

            $mid = $post['member_id'];
            $fsize = $post['filesize'];
            $fname = $post['filename'];
            $tmpname = GALETTE_TEMPIMAGES_PATH . 'ajax_upload_' . $fname;

            $temp = explode('base64,', $post['file']);
            $mime = str_replace('data:', '', trim($temp[0], ';'));
            $raw_file = base64_decode($temp[1]);

            //write temporary file
            $fp = fopen($tmpname, 'w');
            fwrite($fp, $raw_file);
            fclose($fp);

            $adh = new \Galette\Entity\Adherent($this->zdb, (int)$mid);

            $res = $adh->picture->store(
                array(
                    'name'      => $fname,
                    'tmp_name'  => $tmpname,
                    'size'      => $fsize
                ),
                true
            );

            if ($res < 0) {
                $ret['message'] = $adh->picture->getErrorMessage($res);
                $this->flash->addMessage(
                    'error_detected',
                    $ret['message']
                );
            } else {
                $ret['result'] = true;
                $this->flash->addMessage(
                    'success_detected',
                    _T('Member photo has been changed.')
                );
            }

            return $response->withJson($ret);
        }
    )->setName('photoDnd');
});
