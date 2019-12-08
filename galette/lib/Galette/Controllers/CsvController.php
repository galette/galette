<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette CSV controller
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
 * @since     Available since 0.9.4dev - 2019-12-06
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
 * Galette CSV controller
 *
 * @category  Controllers
 * @name      CsvController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-06
 */

class CsvController extends AbstractController
{
    /**
     * Send response
     *
     * @param Response $response PSR Response
     * @param Pdf      $pdf      PDF to output
     *
     * @return Response
     */
    protected function sendResponse(Response $response, Pdf $pdf) :Response
    {
        return $response
            ->withHeader('Content-type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment;filename="' . $pdf->getFileName() . '"')
            ->write($pdf->download());
        return $response;
    }

    /**
     * Exports page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function export(Request $request, Response $response) :Response
    {
        $csv = new \Galette\IO\CsvOut();

        $tables_list = $this->zdb->getTables();
        $parameted = $csv->getParametedExports();
        $existing = $csv->getExisting();

        // display page
        $this->view->render(
            $response,
            'export.tpl',
            array(
                'page_title'        => _T("CVS database Export"),
                'tables_list'       => $tables_list,
                'written'           => $this->flash->getMessage('written_exports'),
                'existing'          => $existing,
                'parameted'         => $parameted,
                'require_dialog'    => true
            )
        );
        return $response;
    }

    /**
     * Proceed exports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doExport(Request $request, Response $response) :Response
    {
        $post = $request->getParsedBody();
        $csv = new CsvOut();
        $written = [];

        if (isset($post['export_tables']) && $post['export_tables'] != '') {
            foreach ($post['export_tables'] as $table) {
                $select = $this->zdb->sql->select($table);
                $results = $this->zdb->execute($select);

                if ($results->count() > 0) {
                    $filename = $table . '_full.csv';
                    $filepath = CsvOut::DEFAULT_DIRECTORY . $filename;
                    $fp = fopen($filepath, 'w');
                    if ($fp) {
                        $res = $csv->export(
                            $results,
                            Csv::DEFAULT_SEPARATOR,
                            Csv::DEFAULT_QUOTE,
                            true,
                            $fp
                        );
                        fclose($fp);
                        $written[] = [
                            'name' => $table,
                            'file' => $filename
                        ];
                    }
                } else {
                    $this->flash->addMessage(
                        'warning_detected',
                        str_replace(
                            '%table',
                            $table,
                            _T("Table %table is empty, and has not been exported.")
                        )
                    );
                }
            }
        }

        if (isset($post['export_parameted']) && $post['export_parameted'] != '') {
            foreach ($post['export_parameted'] as $p) {
                $res = $csv->runParametedExport($p);
                $pn = $csv->getParamedtedExportName($p);
                switch ($res) {
                    case Csv::FILE_NOT_WRITABLE:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("Export file could not be write on disk for '%export'. Make sure web server can write in the exports directory.")
                            )
                        );
                        break;
                    case Csv::DB_ERROR:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("An error occurred running parameted export '%export'.")
                            )
                        );
                        break;
                    case false:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("An error occurred running parameted export '%export'. Please check the logs.")
                            )
                        );
                        break;
                    default:
                        //no error, file has been writted to disk
                        $written[] = [
                            'name' => $pn,
                            'file' => (string)$res
                        ];
                        break;
                }
            }
        }

        if (count($written)) {
            foreach ($written as $ex) {
                $path = $this->router->pathFor('getCsv', ['type' => 'export', 'file' => $ex['file']]);
                $this->flash->addMessage(
                    'written_exports',
                    '<a href="' . $path . '">' . $ex['name'] . ' (' . $ex['file'] . ')</a>'
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('export'));
    }

    /**
     * Imports page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function import(Request $request, Response $response) :Response
    {
        $csv = new CsvIn($this->zdb);
        $existing = $csv->getExisting();
        $dryrun = true;

        // display page
        $this->view->render(
            $response,
            'import.tpl',
            array(
                'page_title'        => _T("CSV members import"),
                'require_dialog'    => true,
                'existing'          => $existing,
                'dryrun'            => $dryrun,
                'import_file'       => $this->flash->getMessage('import_file')[0]
            )
        );
        return $response;
    }

    /**
     * Proceed imports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doImports(Request $request, Response $response) :Response
    {
        $csv = new CsvIn($this->zdb);
        $post = $request->getParsedBody();
        $dryrun = isset($post['dryrun']);
        $res = $csv->import(
            $this->zdb,
            $this->preferences,
            $this->history,
            $post['import_file'],
            $this->members_fields,
            $this->members_fields_cats,
            $dryrun
        );
        if ($res !== true) {
            if ($res < 0) {
                $this->flash->addMessage(
                    'error_detected',
                    $csv->getErrorMessage($res)
                );
                if (count($csv->getErrors()) > 0) {
                    foreach ($csv->getErrors() as $error) {
                        $this->flash->addMessage(
                            'error_detected',
                            $error
                        );
                    }
                }
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    _T("An error occurred importing the file :(")
                );
            }

            $this->flash->addMessage(
                'import_file',
                $post['import_file']
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    '%filename%',
                    $post['import_file'],
                    _T("File '%filename%' has been successfully imported :)")
                )
            );
        }
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('import'));
    }

    /**
     * Get CSV file (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function uploadImportFile(Request $request, Response $response) :Response
    {
        $csv = new CsvIn($this->zdb);
        if (isset($_FILES['new_file'])) {
            if ($_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['new_file']['tmp_name'] !='') {
                    if (is_uploaded_file($_FILES['new_file']['tmp_name'])) {
                        $res = $csv->store($_FILES['new_file']);
                        if ($res < 0) {
                            $this->flash->addMessage(
                                'error_detected',
                                $csv->getErrorMessage($res)
                            );
                        } else {
                            $this->flash->addMessage(
                                'success_detected',
                                _T("Your file has been successfully uploaded!")
                            );
                        }
                    }
                }
            } elseif ($_FILES['new_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $csv->getPhpErrorMessage($_FILES['new_file']['error']),
                    Analog::WARNING
                );
                $this->flash->addMessage(
                    'error_detected',
                    $csv->getPhpErrorMessage(
                        $_FILES['new_file']['error']
                    )
                );
            } elseif (isset($_POST['upload'])) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("No files has been seleted for upload!")
                );
            }
        } else {
            $this->flash->addMessage(
                'warning_detected',
                _T("No files has been uploaded!")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('import'));
    }

    /**
     * Get CSV file (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function getFile(Request $request, Response $response, array $args = []) :Response
    {
        $filename = $args['file'];

        //Exports main contain user confidential data, they're accessible only for
        //admins or staff members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $filepath = $args['type'] === 'export' ?
                CsvOut::DEFAULT_DIRECTORY :
                CsvIn::DEFAULT_DIRECTORY;
            $filepath .= $filename;
            if (file_exists($filepath)) {
                $response = $this->response->withHeader('Content-Description', 'File Transfer')
                    ->withHeader('Content-Type', 'text/csv')
                    ->withHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
                    ->withHeader('Pragma', 'no-cache')
                    ->withHeader('Content-Transfer-Encoding', 'binary')
                    ->withHeader('Expires', '0')
                    ->withHeader('Cache-Control', 'must-revalidate')
                    ->withHeader('Pragma', 'public');

                $stream = fopen('php://memory', 'r+');
                fwrite($stream, file_get_contents($filepath));
                rewind($stream);

                return $response->withBody(new \Slim\Http\Stream($stream));
            } else {
                Analog::log(
                    'A request has been made to get an ' . $args['type'] . ' file named `' .
                    $filename .'` that does not exists.',
                    Analog::WARNING
                );
                $notFound = $this->notFoundHandler;
                return $notFound($request, $response);
            }
        } else {
            Analog::log(
                'A non authorized person asked to retrieve ' . $args['type'] . ' file named `' .
                $filename . '`. Access has not been granted.',
                Analog::WARNING
            );
            $error = $this->errorHandler;
            return $error(
                $request,
                $response->withStatus(403)
            );
        }
    }

    /**
     * Remove CSV file confirmation (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function confirmRemoveFile(Request $request, Response $response, array $args = []) :Response
    {
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor($args['type'])
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove %1$s file %2$s'),
                    $args['type'],
                    $args['file']
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveCsv',
                    [
                        'type' => $args['type'],
                        'file' => $args['file']
                    ]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }

    /**
     * Remove CSV file imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function removeFile(Request $request, Response $response, array $args = []) :Response
    {
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
            $csv = $args['type'] === 'export' ?
                new CsvOut() :
                new CsvIn($this->zdb);
            $res = $csv->remove($args['file']);
            if ($res === true) {
                $success = true;
                $this->flash->addMessage(
                    'success_detected',
                    str_replace(
                        '%export',
                        $args['file'],
                        _T("'%export' file has been removed from disk.")
                    )
                );
            } else {
                $success = false;
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%export',
                        $args['file'],
                        _T("Cannot remove '%export' from disk :/")
                    )
                );
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
    public function contribution(Request $request, Response $response, array $args = []) :Response
    {
        $contribution = new \Galette\Entity\Contribution($this->zdb, $this->login, (int)$args['id']);
        $pdf = new \Galette\IO\PdfContribution($contribution, $this->zdb, $this->preferences);

        return $this->sendResponse($response, $pdf);
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
    public function group(Request $request, Response $response, array $args = []) :Response
    {
        $groups = new \Galette\Repository\Groups($this->zdb, $this->login);

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

        $pdf = new \Galette\IO\PdfGroups($this->preferences);
        $pdf->draw($groups_list, $this->login);
        return $this->sendResponse($response, $pdf);
    }
}
