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
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-05
 */

namespace Galette\Controllers;

use Throwable;
use Slim\Http\Request;
use Slim\Http\Response;
use Analog\Analog;
use Galette\Core\Links;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\PdfModel;
use Galette\Filters\MembersList;
use Galette\IO\Pdf;
use Galette\IO\PdfAttendanceSheet;
use Galette\IO\PdfContribution;
use Galette\IO\PdfGroups;
use Galette\IO\PdfMembersCards;
use Galette\IO\PdfMembersLabels;
use Galette\Repository\Members;
use Galette\Repository\Groups;
use Galette\Repository\PdfModels;

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
     * @param Response $response PSR Response
     * @param Pdf      $pdf      PDF to output
     *
     * @return Response
     */
    protected function sendResponse(Response $response, Pdf $pdf): Response
    {
        return $response
            ->withHeader('Content-type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment;filename="' . $pdf->getFileName() . '"')
            ->write($pdf->download());
    }

    /**
     * Members PDF card
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function membersCards(Request $request, Response $response, array $args = []): Response
    {
        if ($this->session->filter_members) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if (
            isset($args[Adherent::PK])
            && $args[Adherent::PK] > 0
        ) {
            $id_adh = (int)$args[Adherent::PK];
            $deps = ['dynamics' => true];
            if ($this->login->id == $id_adh) {
                $deps['dues'] = true;
            }
            $adh = new Adherent(
                $this->zdb,
                $id_adh,
                $deps
            );
            if (!$adh->canEdit($this->login)) {
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

            //check if member is up to date
            if ($this->login->id == $id_adh) {
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

        $pdf = new PdfMembersCards($this->preferences);
        $pdf->drawCards($members);

        return $this->sendResponse($response, $pdf);
    }

    /**
     * Members PDF label
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function membersLabels(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $get = $request->getQueryParams();

        $session_var = $post['session_var'] ?? $get['session_var'] ?? 'filter_members';

        if (isset($this->session->$session_var)) {
            $filters = $this->session->$session_var;
        } else {
            $filters = new MembersList();
        }

        $members = null;
        if (
            isset($get['from'])
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

        $pdf = new PdfMembersLabels($this->preferences);
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
    public function adhesionForm(Request $request, Response $response, array $args = []): Response
    {
        $id_adh = isset($args[Adherent::PK]) ? (int)$args[Adherent::PK] : null;
        $adh = new Adherent($this->zdb, $id_adh, ['dynamics' => true]);

        if ($id_adh !== null && !$adh->canEdit($this->login)) {
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

        $form = $this->preferences->pref_adhesion_form;
        $pdf = new $form($adh, $this->zdb, $this->preferences);

        return $this->sendResponse($response, $pdf);
    }

    /**
     * PDF attendance sheet configuration page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function attendanceSheetConfig(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();

        if ($this->session->filter_members !== null) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        // check for ajax mode
        $ajax = false;
        if (
            $request->isXhr()
            || isset($post['ajax'])
            && $post['ajax'] == 'true'
        ) {
            $ajax = true;

            //retrieve selected members
            $selection = (isset($post['selection'])) ? $post['selection'] : array();

            $filters->selected = $selection;
            $this->session->filter_members = $filters;
        } else {
            $selection = $filters->selected;
        }

        // display page
        $this->view->render(
            $response,
            'attendance_sheet_details.tpl',
            [
                'page_title'    => _T("Attendance sheet configuration"),
                'ajax'          => $ajax,
                'selection'     => $selection
            ]
        );
        return $response;
    }

    /**
     * PDF attendance sheet
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function attendanceSheet(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if ($this->session->filter_members !== null) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        //retrieve selected members
        $selection = (isset($post['selection'])) ? $post['selection'] : array();

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
            'sheet_date' => $post['sheet_date'] ?? null
        ];
        $pdf = new PdfAttendanceSheet($this->zdb, $this->preferences, $data);
        //with or without images?
        if (isset($post['sheet_photos']) && $post['sheet_photos'] === '1') {
            $pdf->withImages();
        }
        $pdf->drawSheet($members);

        return $this->sendResponse($response, $pdf);
    }

    /**
     * Contribution PDF
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function contribution(Request $request, Response $response, array $args = []): Response
    {
        $contribution = new Contribution($this->zdb, $this->login, (int)$args['id']);
        if ($contribution->id == '') {
            //not possible to load contribution, exit
            $this->flash->addMessage(
                'error_detected',
                str_replace(
                    '%id',
                    $args['id'],
                    _T("Unable to load contribution #%id!")
                )
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor(
                    'contributions',
                    ['type' => 'contributions']
                ));
        } else {
            $pdf = new PdfContribution($contribution, $this->zdb, $this->preferences);
            return $this->sendResponse($response, $pdf);
        }
    }

    /**
     * Groups PDF
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function group(Request $request, Response $response, array $args = []): Response
    {
        $groups = new Groups($this->zdb, $this->login);

        $groups_list = null;
        if (isset($args['id'])) {
            $groups_list = $groups->getList(true, $args['id']);
        } else {
            $groups_list = $groups->getList();
        }

        if (!is_array($groups_list) || count($groups_list) < 1) {
            Analog::log(
                'An error has occurred, unable to get groups list.',
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

        return $this->sendResponse($response, $pdf);
    }

    /**
     * PDF models list
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function models(Request $request, Response $response, array $args = []): Response
    {
        $id = 1;
        if (isset($_POST[PdfModel::PK])) {
            $id = (int)$_POST[PdfModel::PK];
        } elseif (isset($args['id'])) {
            $id = (int)$args['id'];
        }


        $ms = new PdfModels($this->zdb, $this->preferences, $this->login);
        $models = $ms->getList();

        $model = null;
        foreach ($models as $m) {
            if ($m->id === $id) {
                $model = $m;
                break;
            }
        }

        $tpl = null;
        $params = ['model' => $model];

        //Render directly template if we called from ajax,
        //render in a full page otherwise
        if (
            $request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $tpl = 'gestion_pdf_content.tpl';
        } else {
            $tpl = 'gestion_pdf.tpl';
            $params += [
                'page_title'        => _T("PDF models"),
                'models'            => $models
            ];
        }

        // display page
        $this->view->render(
            $response,
            $tpl,
            $params
        );
        return $response;
    }

    /**
     * Store PDF models
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function storeModels(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $type = null;
        if (isset($post['model_type'])) {
            $type = (int)$post['model_type'];
        }

        $error_detected = [];
        if ($type === null) {
            $error_detected[] = _T("Missing PDF model type!");
        } else {
            $class = PdfModel::getTypeClass($type);
            if (isset($post[PdfModel::PK])) {
                $model = new $class($this->zdb, $this->preferences, (int)$_POST[PdfModel::PK]);
            } else {
                $model = new $class($this->zdb, $this->preferences);
            }

            try {
                $model->header = $post['model_header'];
                $model->footer = $post['model_footer'];
                $model->type = $type;
                if (isset($post['model_body'])) {
                    $model->body = $post['model_body'];
                }
                if (isset($post['model_title'])) {
                    $model->title = $post['model_title'];
                }
                if (isset($post['model_body'])) {
                    $model->subtitle = $post['model_subtitle'];
                }
                if (isset($post['model_styles'])) {
                    $model->styles = $post['model_styles'];
                }
                $res = $model->store();
                if ($res === true) {
                    $this->flash->addMessage(
                        'success_detected',
                        _T("Model has been successfully stored!")
                    );
                } else {
                    $error_detected[] = _T("Model has not been stored :(");
                }
            } catch (Throwable $e) {
                $error_detected[] = $e->getMessage();
            }
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('pdfModels', ['id' => $model->id]));
    }


    /**
     * Get direct document
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function directlinkDocument(Request $request, Response $response, array $args = []): Response
    {
        $hash = $args['hash'];
        $post = $request->getParsedBody();
        $email = $post['email'];

        $links = new Links($this->zdb);
        $valid = $links->isHashValid($hash, $email);

        if ($valid === false) {
            $this->flash->addMessage(
                'error_detected',
                _T("Invalid link!")
            );

            return $response->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('directlink', ['hash' => $hash]));
        }

        $target = $valid[0];
        $id = (int)$valid[1];

        //get user information (like id...) from DB since its missing
        $select = $this->zdb->select(Adherent::TABLE, 'a');
        $select->where(['email_adh' => $post['email']]);
        $results = $this->zdb->execute($select);
        $row = $results->current();

        //create a new login instance, to not break current session if any
        //this will be passed directly to Contribution constructor
        $login = new \Galette\Core\Login(
            $this->zdb,
            $this->i18n,
            $this->session
        );
        $login->id = (int)$row['id_adh'];

        if ($target === Links::TARGET_MEMBERCARD) {
            $m = new Members();
            $members = $m->getArrayList(
                [$id],
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
                    ->withHeader('Location', $this->router->pathFor('directlink', ['hash' => $hash]));
            }

            $pdf = new PdfMembersCards($this->preferences);
            $pdf->drawCards($members);
        } else {
            $contribution = new Contribution($this->zdb, $login, $id);
            if ($contribution->id == '') {
                //not possible to load contribution, exit
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%id',
                        $args['id'],
                        _T("Unable to load contribution #%id!")
                    )
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor(
                        'directlink',
                        ['hash' => $hash]
                    ));
            } else {
                $pdf = new PdfContribution($contribution, $this->zdb, $this->preferences);
            }
        }

        return $this->sendResponse($response, $pdf);
    }
}
