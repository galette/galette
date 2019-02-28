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

use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Members;
use Galette\Filters\MembersList;

$app->group('/ajax', function () use ($authenticate) {
    $this->get(
        '/messages',
        function ($request, $response) {
            $this->view->render(
                $response,
                'ajax_messages.tpl'
            );
            return $response;
        }
    )->setName('ajaxMessages');

    $this->post(
        'photo',
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

            $adh = new Adherent($this->zdb, (int)$mid);

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

    $this->post(
        '/suggest/towns',
        function ($request, $response) {
            $post = $request->getParsedBody();

            $ret = [];

            try {
                $select1 = $this->zdb->select(Adherent::TABLE);
                $select1->columns(['ville_adh']);
                $select1->where->like('ville_adh', '%' . html_entity_decode($post['term']) . '%');

                $select2 = $this->zdb->select(Adherent::TABLE);
                $select2->columns(['lieu_naissance']);
                $select2->where->like('lieu_naissance', '%' . html_entity_decode($post['term']) . '%');

                $select1->combine($select2);

                $select = $this->zdb->sql->select();
                $select->from(['sub' => $select1])
                    ->order('ville_adh ASCC')
                    ->limit(10);

                $towns = $this->zdb->execute($select);

                foreach ($towns as $town) {
                    $ret[] = [
                        'id'    => $town->ville_adh,
                        'label' => $town->ville_adh
                    ];
                }
            } catch (\Exception $e) {
                Analog::log(
                    'Something went wrong is towns suggestion: ' . $e->getMessage(),
                    Analog::WARNING
                );
                throw $e;
            }

            return $response->withJson($ret);
        }
    )->setName('suggestTown');

    $this->post(
        '/suggest/countries',
        function ($request, $response) {
            $post = $request->getParsedBody();

            $ret = [];

            try {
                $select = $this->zdb->select(Adherent::TABLE);
                $select->columns(['pays_adh']);
                $select->where->like('pays_adh', '%' . html_entity_decode($post['term']) . '%');
                $select->limit(10);
                $select->order(['pays_adh ASC']);

                $towns = $this->zdb->execute($select);

                foreach ($towns as $town) {
                    $ret[] = [
                        'id'    => $town->pays_adh,
                        'label' => $town->pays_adh
                    ];
                }
            } catch (\Exception $e) {
                Analog::log(
                    'Something went wrong is countries suggestion: ' . $e->getMessage(),
                    Analog::WARNING
                );
                throw $e;
            }

            return $response->withJson($ret);
        }
    )->setName('suggestCountry');

    $this->get(
        '/telemetry/infos',
        function ($request, $response) {
            $telemetry = new \Galette\Util\Telemetry(
                $this->zdb,
                $this->preferences,
                $this->plugins
            );
            $body = $response->getBody();
            $body->write('<pre>' . json_encode($telemetry->getTelemetryInfos(), JSON_PRETTY_PRINT)  . '</pre>');
            return $response;
        }
    )->setName('telemetryInfos')->add($authenticate);

    $this->post(
        '/telemetry/send',
        function ($request, $response) {
            $telemetry = new \Galette\Util\Telemetry(
                $this->zdb,
                $this->preferences,
                $this->plugins
            );
            try {
                $result = $telemetry->send();
                $message = _T('Telemetry informations has been sent. Thank you!');
                $result = [
                    'success'   => true,
                    'message'   => $message
                ];
            } catch (\Exception $e) {
                $result = [
                    'success'   => false,
                    'message'   => $e->getMessage()
                ];
            }
            return $response->withJson($result);
        }
    )->setName('telemetrySend')->add($authenticate);

    $this->get(
        '/telemetry/registered',
        function ($request, $response) {
            $this->preferences->pref_registration_date = date('Y-m-d H:i:s');
            $this->preferences->store();
            return $response->withJson(['message' => _T('Thank you for registering!')]);
        }
    )->setName('setRegistered')->add($authenticate);

    $this->post(
        '/contribution/dates',
        function ($request, $response) {
            $post = $request->getParsedBody();

            // contribution types
            $ct = new ContributionsTypes($this->zdb);
            $contributions_types = $ct->getList(true);

            $contrib = new Contribution(
                $this->zdb,
                $this->login,
                [
                    'type'  => array_keys($contributions_types)[$post['fee_id']],
                    'adh'   => (int)$post['member_id']
                ]
            );
            $contribution['duree_mois_cotis'] = $this->preferences->pref_membership_ext;

            return $response->withJson([
                'date_debut_cotis'  => $contrib->begin_date,
                'date_fin_cotis'    => $contrib->end_date
            ]);
        }
    )->setName('contributionDates')->add($authenticate);

    $this->post(
        '/contribution/members[/{page:\d+}[/{search}]]',
        function ($request, $response, $args) {
            $post = $request->getParsedBody();
            $filters = new MembersList();
            if (isset($post['page'])) {
                $filters->current_page = (int)$post['page'];
            } elseif (isset($args['page'])) {
                $filters->current_page = (int)$args['page'];
            }

            $term = null;
            if (isset($args['search'])) {
                $term = $args['search'];
            }
            if (isset($post['search'])) {
                $term = $post['search'];
            }
            if ($term !== null) {
                $filters->filter_str = $term;
                if (is_numeric($term)) {
                    $filters->field_filter = Members::FILTER_NUMBER;
                }
            }

            $m = new Members($filters);
            $required_fields = array(
                'id_adh',
                'nom_adh',
                'prenom_adh'
            );
            $list_members = $m->getList(false, $required_fields, true);

            $members = [];
            if (count($list_members) > 0) {
                foreach ($list_members as $member) {
                    $pk = Adherent::PK;
                    $sname = mb_strtoupper($member->nom_adh, 'UTF-8') .
                        ' ' . ucwords(mb_strtolower($member->prenom_adh, 'UTF-8')) .
                        ' (' . $member->id_adh . ')';
                    $members[] = [
                        'value' => $member->$pk,
                        'text'  => $sname
                    ];
                }
            }

            return $response->withJson([
                'members'   => $members,
                'count'     => count($members)
            ]);
        }
    )->setName('contributionMembers')->add($authenticate);
});
