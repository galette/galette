<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Analog\Analog;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Transaction;
use Galette\Repository\Contributions;
use Galette\Repository\Members;

/**
 * Galette transactions controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class TransactionsController extends ContributionsController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?string  $type     Contribution type
     *
     * @return Response
     */
    public function add(Request $request, Response $response, ?string $type = null): Response
    {
        return $this->edit($request, $response, null, 'add');
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?string  $type     Contribution type
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, ?string $type = null): Response
    {
        $trans = new Transaction($this->zdb, $this->login);
        if (!$trans->canCreate($this->login)) {
            Analog::log(
                'Trying to add transaction without appropriate ACLs',
                Analog::WARNING
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                );
        }

        return $this->storeTransaction($request, $response, 'add', $trans);
    }

    // /CRUD - Create
    // CRUD - Read

    //ContributionsController manages both lists and filter

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?integer $id       Transaction id
     * @param ?string  $action   Action
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, ?int $id = null, string|null $action = 'edit'): Response
    {
        if ($this->session->transaction !== null) {
            $trans = $this->session->transaction;
            $this->session->transaction = null;
        } else {
            $trans = new Transaction($this->zdb, $this->login);
        }

        // flagging required fields
        $required = [
            'trans_amount'  =>  1,
            'trans_date'    =>  1,
            'trans_desc'    =>  1,
            'id_adh'        =>  1
        ];

        if ($action === 'edit') {
            // initialize transactions structure with database values
            $trans->load($id);
            if ($trans->id == '') {
                //not possible to load transaction, exit
                //not possible to load contribution, exit
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%id',
                        (string)$id,
                        _T("Unable to load transaction #%id!")
                    )
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor(
                        'contributions',
                        ['type' => 'transactions']
                    ));
            }

            if (!$trans->canEdit($this->login) && !$trans->canAttachAndDetach($this->login)) {
                Analog::log(
                    'Trying to edit transaction without appropriate ACLs',
                    Analog::WARNING
                );
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('slash')
                    );
            }
        } else {
            if (!$trans->canCreate($this->login)) {
                Analog::log(
                    'Trying to add transaction without appropriate ACLs',
                    Analog::WARNING
                );
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('slash')
                    );
            }
        }

        // template variable declaration
        $title = _T("Transaction");
        if ($action === 'edit') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        $params = [
            'page_title'        => $title,
            'required'          => $required,
            'transaction'       => $trans,
            'documentation'     => 'usermanual/contributions.html#transactions'
        ];

        if ($trans->id != '') {
            $contribs = new Contributions($this->zdb, $this->login);
            $params['contribs'] = $contribs->getListFromTransaction($trans->id);
        }
        $params['contribution'] = new Contribution($this->zdb, $this->login);

        // members
        $m = new Members();
        $members = $m->getDropdownMembers(
            $this->zdb,
            $this->login,
            $trans->member
        );

        $params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];
        $params['autocomplete'] = true;

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            'pages/transaction_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Transaction id
     * @param ?string  $type     Transaction type
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id, ?string $type = null): Response
    {
        $trans = new Transaction($this->zdb, $this->login);

        // initialize transactions structure with database values
        if (!$trans->load($id)) {
            //not possible to load transaction, exit
            $this->flash->addMessage(
                'error_detected',
                str_replace(
                    '%id',
                    (string)$id,
                    _T("Unable to load transaction #%id!")
                )
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor(
                    'contributions',
                    ['type' => 'transactions']
                ));
        }
        if (!$trans->canEdit($this->login)) {
            Analog::log(
                'Trying to edit transaction without appropriate ACLs',
                Analog::WARNING
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                );
        }

        return $this->storeTransaction($request, $response, 'edit', $trans, $id);
    }

    /**
     * Store contribution (new or existing)
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string      $action   Action ('edit' or 'add')
     * @param Transaction $trans    Transaction instance
     * @param ?integer    $id       Contribution id
     *
     * @return Response
     */
    public function storeTransaction(Request $request, Response $response, string $action, Transaction $trans, ?int $id = null): Response
    {
        $post = $request->getParsedBody();
        // flagging required fields
        $required = [
            'trans_amount'  =>  1,
            'trans_date'    =>  1,
            'trans_desc'    =>  1,
            'id_adh'        =>  1
        ];
        $disabled = [];

        $args = [];
        if ($id !== null) {
            $args['id'] = (string)$id;
        }
        $redirect_url = $this->routeparser->urlFor(
            ($action == 'add' ? 'addTransaction' : 'editTransaction'),
            $args
        );

        // regular fields
        $valid = $trans->check($post, $required, $disabled);
        //store entity in session
        $this->session->transaction = $trans;

        if ($valid !== true) {
            return $this->redirectWithErrors(
                $response,
                $valid,
                $redirect_url
            );
        }

        //all goes well, we can proceed
        if (!$trans->store($this->history)) {
            //something went wrong :'(
            return $this->redirectWithErrors(
                $response,
                [_T("An error occurred while storing the transaction.")],
                $redirect_url
            );
        }

        $this->session->transaction = null;
        $this->flash->addMessage(
            'success_detected',
            _T("Transaction has been successfully stored")
        );

        //get back to transactions list
        $redirect_url = $this->routeparser->urlFor('contributions', ['type' => 'transactions']);
        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            //or slash URL for non staff nor admin
            $redirect_url = $this->routeparser->urlFor('slash');
        }

        $files_res = $trans->handleFiles($_FILES);
        if (is_array($files_res)) {
            foreach ($files_res as $res) {
                $this->flash->addMessage(
                    'error_detected',
                    $res
                );
            }
        }

        if (isset($post['contrib_type']) && $trans->getMissingAmount() > 0) {
            $rparams = [
                'type' => $post['contrib_type']
            ];

            if (isset($trans->member)) {
                $rparams['id_adh'] = (string)$trans->member;
            }

            $redirect_url = $this->routeparser->urlFor(
                'addContribution',
                $rparams
            ) . '?' . Transaction::PK . '=' . $trans->id .
                '&' . Adherent::PK . '=' . $trans->member;
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }

    /**
     * Attach action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Transaction id
     * @param integer  $cid      Contribution id
     *
     * @return Response
     */
    public function attach(Request $request, Response $response, int $id, int $cid): Response
    {
        $transaction = new Transaction($this->zdb, $this->login, $id);
        $done = false;
        if ($transaction->canAttachAndDetach($this->login)) {
            $contribution = new Contribution($this->zdb, $this->login, $cid);
            if ($contribution->canShow($this->login) && $contribution->setTransactionPart($id)) {
                $done = true;
                $this->flash->addMessage(
                    'success_detected',
                    _T("Contribution has been successfully attached to current transaction")
                );
            }
        }

        if (!$done) {
            $this->flash->addMessage(
                'error_detected',
                _T("Unable to attach contribution to transaction")
            );
        }

        $redirect_url = $this->routeparser->urlFor('slash');
        if ($transaction->canEdit($this->login)) {
            $redirect_url = $this->routeparser->urlFor(
                'editTransaction',
                ['id' => (string)$id]
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }

    /**
     * Attach action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Transaction id
     * @param integer  $cid      Contribution id
     *
     * @return Response
     */
    public function detach(Request $request, Response $response, int $id, int $cid): Response
    {
        $transaction = new Transaction($this->zdb, $this->login, $id);
        $done = false;
        if ($transaction->canAttachAndDetach($this->login)) {
            $contribution = new Contribution($this->zdb, $this->login, $cid);
            if ($contribution->canShow($this->login) && $contribution->unsetTransactionPart($id)) {
                $done = true;
                $this->flash->addMessage(
                    'success_detected',
                    _T("Contribution has been successfully detached from current transaction")
                );
            }
        }

        if (!$done) {
            $this->flash->addMessage(
                'error_detected',
                _T("Unable to detach contribution from transaction")
            );
        }

        $redirect_url = $this->routeparser->urlFor('slash');
        if ($transaction->canEdit($this->login)) {
            $redirect_url = $this->routeparser->urlFor(
                'editTransaction',
                ['id' => (string)$id]
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }

    // /CRUD - Update
    // CRUD - Delete

    //all inherited

    // /CRUD - Delete
    // /CRUD
}
