<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette PDF controller
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-05
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Filters\MembersList;
use Galette\Repository\Members;
use Galette\IO\Pdf;

/**
 * Galette PDF controller
 *
 * @category  Controllers
 * @name      GaletteController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-05
 */

class PdfController extends AbstractController
{
    /**
     * Send response
     *
     * @param Pdf $pdf PDF to output
     *
     * @return Response
     */
    protected function sendResponse(Pdf $pdf) :Response
    {
        return $response
            ->withHeader('Content-type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment;filename="' . $pdf->getFileName() . '"')
            ->write($pdf->download());
        return $response;
    }

    /**
     * PDF card
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function pdfCard(Request $request, Response $response, array $args = []) :Response
    {
        if ($this->session->filter_members) {
            $filters =  $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if (isset($args[Adherent::PK])
            && $args[Adherent::PK] > 0
        ) {
            $id_adh = $args[Adherent::PK];
            $denied = false;
            if ($this->login->id != $id_adh
                && !$this->login->isAdmin()
                && !$this->login->isStaff()
                && !$this->login->isGroupManager()
            ) {
                $denied = true;
            }

            if (!$this->login->isAdmin() && !$this->login->isStaff() && $this->login->id != $id_adh) {
                if ($this->login->isGroupManager()) {
                    $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true]);
                    //check if current logged in user can manage loaded member
                    $groups = $adh->groups;
                    $can_manage = false;
                    foreach ($groups as $group) {
                        if ($this->login->isGroupManager($group->getId())) {
                            $can_manage = true;
                            break;
                        }
                    }
                    if ($can_manage !== true) {
                        Analog::log(
                            'Logged in member ' . $this->login->login .
                            ' has tried to load member #' . $adh->id .
                            ' but do not manage any groups he belongs to.',
                            Analog::WARNING
                        );
                        $denied = true;
                    }
                } else {
                    $denied = true;
                }
            }

            if ($denied) {
                //requested member cannot be managed. Load logged in user
                $id_adh = (int)$this->login->id;
            }

            //check if member is up to date
            if ($this->login->id == $id_adh) {
                $adh = new Adherent($this->zdb, (int)$id_adh, ['dues' => true]);
                if (!$adh->isUp2Date()) {
                    Analog::log(
                        'Member ' . $id_adh . ' is not up to date; cannot get his PDF member card',
                        Analog::WARNING
                    );
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('slash'));
                }
            }

            // If we are called from a member's card, get unique id value
            $unique = $id_adh;
        } else {
            if (count($filters->selected) == 0) {
                Analog::log(
                    'No member selected to generate members cards',
                    Analog::INFO
                );
                $this->flash->addMessage(
                    'error_detected',
                    _T("No member was selected, please check at least one name.")
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('members'));
            }
        }

        // Fill array $selected with selected ids
        $selected = array();
        if (isset($unique) && $unique) {
            $selected[] = $unique;
        } else {
            $selected = $filters->selected;
        }

        $m = new Members();
        $members = $m->getArrayList(
            $selected,
            array('nom_adh', 'prenom_adh'),
            true
        );

        if (!is_array($members) || count($members) < 1) {
            Analog::log(
                'An error has occurred, unable to get members list.',
                Analog::ERROR
            );

            $this->flash->addMessage(
                'error_detected',
                _T("Unable to get members list.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $pdf = new \Galette\IO\PdfMembersCards($this->preferences);
        $pdf->drawCards($members);

        return $this->sendResponse($response, $pdf);
    }

    /**
     * PDF label
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function pdfLabel(Request $request, Response $response) :Response
    {
        $get = $request->getQueryParams();

        if ($this->session->filter_reminders_labels) {
            $filters =  $this->session->filter_reminders_labels;
            unset($this->session->filter_reminders_labels);
        } elseif ($this->session->filter_members) {
            $filters =  $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        $members = null;
        if (isset($get['from'])
            && $get['from'] === 'mailing'
        ) {
            //if we're from mailing, we have to retrieve
            //its unreachables members for labels
            $mailing = $this->session->mailing;
            $members = $mailing->unreachables;
        } else {
            if (count($filters->selected) == 0) {
                Analog::log('No member selected to generate labels', Analog::INFO);
                $this->flash->addMessage(
                    'error_detected',
                    _T("No member was selected, please check at least one name.")
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('members'));
            }

            $m = new Members();
            $members = $m->getArrayList(
                $filters->selected,
                array('nom_adh', 'prenom_adh')
            );
        }

        if (!is_array($members) || count($members) < 1) {
            Analog::log(
                'An error has occurred, unable to get members list.',
                Analog::ERROR
            );

            $this->flash->addMessage(
                'error_detected',
                _T("Unable to get members list.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $pdf = new \Galette\IO\PdfMembersLabels($this->preferences);
        $pdf->drawLabels($members);

        return $this->sendResponse($response, $pdf);
    }

    /**
     * PDF adhesion form
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function adhesionForm(Request $request, Response $response, array $args = []) :Response
    {
        $id_adh = (int)$args[Adherent::PK] ?? null;

        $denied = false;
        if ($this->login->id != $id_adh
            && !$this->login->isAdmin()
            && !$this->login->isStaff()
            && !$this->login->isGroupManager()
        ) {
            $denied = true;
        }

        if (!$this->login->isAdmin() && !$this->login->isStaff() && $this->login->id != $id_adh) {
            if ($this->login->isGroupManager()) {
                $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true]);
                //check if current logged in user can manage loaded member
                $groups = $adh->groups;
                $can_manage = false;
                foreach ($groups as $group) {
                    if ($this->login->isGroupManager($group->getId())) {
                        $can_manage = true;
                        break;
                    }
                }
                if ($can_manage !== true) {
                    Analog::log(
                        'Logged in member ' . $this->login->login .
                        ' has tried to load member #' . $adh->id .
                        ' but do not manage any groups he belongs to.',
                        Analog::WARNING
                    );
                    $denied = true;
                }
            } else {
                $denied = true;
            }
        }
        //load a member if an id has been requested. Otherwise, otherwise emtpy for
        $adh = null;
        if (isset($args['id'])) {
            if ($denied) {
                //requested member cannot be managed. Load logged in user
                $id_adh = (int)$this->login->id;
            }
            $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true]);
        }

        $form = $this->preferences->pref_adhesion_form;
        $pdf = new $form($adh, $this->zdb, $this->preferences);

        return $this->sendResponse($response, $pdf);
    }

    /**
     * PDF attendance sheet
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function attendanceSheet(Request $request, Response $response, array $args = []) :Response
    {
        $post = $request->getParsedBody();

        if ($this->session->filter_members !== null) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        //retrieve selected members
        $selection = (isset($post['selection']) ) ? $post['selection'] : array();

        $filters->selected = $selection;
        $this->session->filter_members = $filters;

        if (count($filters->selected) == 0) {
            Analog::log('No member selected to generate attendance sheet', Analog::INFO);
            $this->flash->addMessage(
                'error_detected',
                _T("No member selected to generate attendance sheet")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $m = new Members();
        $members = $m->getArrayList(
            $filters->selected,
            array('nom_adh', 'prenom_adh'),
            true
        );

        if (!is_array($members) || count($members) < 1) {
            Analog::log('No member selected to generate attendance sheet', Analog::INFO);
            $this->flash->addMessage(
                'error_detected',
                _T("No member selected to generate attendance sheet")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $doc_title = _T("Attendance sheet");
        if (isset($post['sheet_type']) && trim($post['sheet_type']) != '') {
            $doc_title = $post['sheet_type'];
        }

        $data = [
            'doc_title' => $doc_title,
            'title'     => $post['sheet_title'] ?? null,
            'subtitle'  => $post['sheet_sub_title'] ?? null,
            'sheet_date'=> $post['sheet_date'] ?? null
        ];
        $pdf = new Galette\IO\PdfAttendanceSheet($this->zdb, $this->preferences, $data);
        //with or without images?
        if (isset($post['sheet_photos']) && $post['sheet_photos'] === '1') {
            $pdf->withImages();
        }
        $pdf->drawSheet($members);

        return $this->sendResponse($pdf);
    }
}
