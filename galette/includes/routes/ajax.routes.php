<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Ajax routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2021 The Galette Team
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
 * @copyright 2014-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
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
            $this->get('view')->render(
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

            if (
                !isset($post['member_id'])
                || !isset($post['file'])
                || !isset($post['filename'])
                || !isset($post['filesize'])
            ) {
                $this->get('flash')->addMessage(
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
            $raw_file = base64_decode($temp[1]);

            //write temporary file
            $fp = fopen($tmpname, 'w');
            fwrite($fp, $raw_file);
            fclose($fp);

            $adh = new Adherent($this->get('zdb'), (int)$mid);

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
                $this->get('flash')->addMessage(
                    'error_detected',
                    $ret['message']
                );
            } else {
                $ret['result'] = true;
                $this->get('flash')->addMessage(
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
                $select1 = $this->get('zdb')->select(Adherent::TABLE);
                $select1->columns(['ville_adh']);
                $select1->where->like('ville_adh', '%' . html_entity_decode($post['term']) . '%');

                $select2 = $this->get('zdb')->select(Adherent::TABLE);
                $select2->columns(['lieu_naissance']);
                $select2->where->like('lieu_naissance', '%' . html_entity_decode($post['term']) . '%');

                $select1->combine($select2);

                $select = $this->get('zdb')->sql->select();
                $select->from(['sub' => $select1])
                    ->order('ville_adh ASCC')
                    ->limit(10);

                $towns = $this->get('zdb')->execute($select);

                foreach ($towns as $town) {
                    $ret[] = [
                        'id'    => $town->ville_adh,
                        'label' => $town->ville_adh
                    ];
                }
            } catch (Throwable $e) {
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
                $select = $this->get('zdb')->select(Adherent::TABLE);
                $select->columns(['pays_adh']);
                $select->where->like('pays_adh', '%' . html_entity_decode($post['term']) . '%');
                $select->limit(10);
                $select->order(['pays_adh ASC']);

                $towns = $this->get('zdb')->execute($select);

                foreach ($towns as $town) {
                    $ret[] = [
                        'id'    => $town->pays_adh,
                        'label' => $town->pays_adh
                    ];
                }
            } catch (Throwable $e) {
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
                $this->get('zdb'),
                $this->get('preferences'),
                $this->get('plugins')
            );
            $body = $response->getBody();
            $body->write('<pre>' . json_encode($telemetry->getTelemetryInfos(), JSON_PRETTY_PRINT) . '</pre>');
            return $response;
        }
    )->setName('telemetryInfos')->add($authenticate);

    $this->post(
        '/telemetry/send',
        function ($request, $response) {
            $telemetry = new \Galette\Util\Telemetry(
                $this->get('zdb'),
                $this->get('preferences'),
                $this->get('plugins')
            );
            try {
                $telemetry->send();
                $message = _T('Telemetry information has been sent. Thank you!');
                $result = [
                    'success'   => true,
                    'message'   => $message
                ];
            } catch (Throwable $e) {
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
            $this->get('preferences')->pref_registration_date = date('Y-m-d H:i:s');
            $this->get('preferences')->store();
            return $response->withJson(['message' => _T('Thank you for registering!')]);
        }
    )->setName('setRegistered')->add($authenticate);

    $this->post(
        '/contribution/dates',
        function ($request, $response) {
            $post = $request->getParsedBody();

            $contrib = new Contribution(
                $this->get('zdb'),
                $this->get('login'),
                [
                    'type'  => (int)$post['fee_id'],
                    'adh'   => (int)$post['member_id']
                ]
            );
            $contribution['duree_mois_cotis'] = $this->get('preferences')->pref_membership_ext;

            return $response->withJson([
                'date_debut_cotis'  => $contrib->begin_date,
                'date_fin_cotis'    => $contrib->end_date
            ]);
        }
    )->setName('contributionDates')->add($authenticate);

    $this->post(
        '/contribution/members[/{page:\d+}[/{search}]]',
        function ($request, $response, int $page = null, $search = null) {
            $post = $request->getParsedBody();
            $filters = new MembersList();
            if (isset($post['page'])) {
                $filters->current_page = (int)$post['page'];
            } elseif ($page !== null) {
                $filters->current_page = $page;
            }

            if (isset($post['search'])) {
                $search = $post['search'];
            }
            if ($search !== null) {
                $filters->filter_str = $search;
                if (is_numeric($search)) {
                    $filters->field_filter = Members::FILTER_ID;
                }
            }

            $m = new Members($filters);
            $list_members = $m->getSelectizedMembers($this->get('zdb'), $this->get('login'));

            $members = [];
            if (count($list_members) > 0) {
                foreach ($list_members as $pk => $member) {
                    $members[] = [
                        'value' => $pk,
                        'text'  => $member
                    ];
                }
            }

            return $response->withJson([
                'members'   => $members,
                'count'     => count($members)
            ]);
        }
    )->setName('contributionMembers')->add($authenticate);

    $this->post(
        '/password/strength',
        function ($request, $response) {
            //post params may be passed from security tab test password
            $post = $request->getParsedBody();

            if (isset($post['pref_password_length'])) {
                $this->get('preferences')->pref_password_length = $post['pref_password_length'];
            }

            if (isset($post['pref_password_strength'])) {
                $this->get('preferences')->pref_password_strength = $post['pref_password_strength'];
            }

            if (isset($post['pref_password_blacklist'])) {
                $this->get('preferences')->pref_password_blacklist = $post['pref_password_blacklist'];
            }

            $pass = new \Galette\Util\Password($this->get('preferences'));
            $valid = $pass->isValid($post['value']);

            return $response->withJson(
                [
                    'valid'     => $valid,
                    'score'     => $pass->getStrenght(),
                    'errors'    => $pass->getErrors(),
                    'warnings'  => ($valid ? $pass->getStrenghtErrors() : null)
                ]
            );
        }
    )->setName('checkPassword');
});
