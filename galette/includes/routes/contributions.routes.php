<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions related routes
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

use Galette\Entity\Contribution;
use Galette\Repository\Contributions;
use Galette\Entity\Transaction;
use Galette\Repository\Transactions;
use Galette\Entity\DynamicFields;
use Galette\Repository\Members;
use Galette\Entity\Adherent;

$app->get(
    '/contributions[/{option:page|order}/{value:\d+}]',
    function ($request, $response, $args) {
        $ajax = false;
        if ($request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        //$id = $args['id'];
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if ($this->session->contributions !== null) {
            $contribs = $this->session->contributions;
        } else {
            $contribs = new Contributions();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $contribs->current_page = (int)$value;
                    break;
                case 'order':
                    $contribs->orderby = $value;
                    break;
            }
        }

        /*if ($ajax === true) {
            $contribs->filtre_transactions = true;
            if (isset($_POST['max_amount'])) {
                $contribs->max_amount = (int)$_POST['max_amount'];
            } elseif ($_GET['max_amount']) {
                $contribs->max_amount = (int)$_GET['max_amount'];
            }
        } else {
            $contribs->max_amount = null;
        }*/

        /*if (($this->login->isAdmin() || $this->login->isStaff())) {
            if ($id == 'all') {
                $contribs->filtre_cotis_adh = null;
            } else {
                $contribs->filtre_cotis_adh = $id;
            }
        }*/

        /*if ($this->login->isAdmin() || $this->login->isStaff()) {
            //delete contributions
            if (isset($_GET['sup']) || isset($_POST['delete'])) {
                if ( isset($_GET['sup']) ) {
                    $contribs->removeContributions($_GET['sup']);
                } else if ( isset($_POST['contrib_sel']) ) {
                    $contribs->removeContributions($_POST['contrib_sel']);
                }
            }
        }*/

        $this->session->contributions = $contribs;
        $list_contribs = $contribs->getContributionsList(true);

        //assign pagination variables to the template and add pagination links
        $contribs->setSmartyPagination($this->router, $this->view->getSmarty());

        /*if ( $contribs->filtre_cotis_adh != null && !$ajax ) {
            $member = new Adherent();
            $member->load($contribs->filtre_cotis_adh);
            $tpl->assign('member', $member);
        }*/

        // display page
        $this->view->render(
            $response,
            'gestion_contributions.tpl',
            array(
                'page_title'            => _T("Contributions management"),
                'require_dialog'        => true,
                'require_calendar'      => true,
                'max_amount'            => $contribs->max_amount,
                'list_contribs'         => $list_contribs,
                'contributions'         => $contribs,
                'nb_contributions'      => $contribs->getCount(),
                'mode'                  => 'std'
            )
        );
        return $response;
    }
)->setName(
    'contributions'
)->add($authenticate);

$app->get(
    '/contributions/{action:add|edit}[/{id:\d+}]',
    function ($request, $response, $args) {
        //
    }
)->setName('contribution')->add($authenticate);

$app->get(
    '/transactions',
    function ($request, $response) {
        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            $id_adh = $this->login->id;
        } else {
            $id_adh = get_numeric_form_value('id_adh', '');
        }

        $filtre_id_adh = '';

        /*if ($this->session->transactions !== null) {
            $trans = $this->session->transactions;
        } else {*/
            $trans = new Galette\Repository\Transactions($this->zdb, $this->login);
        /*}*/

        /*if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
            $trans->current_page = (int)$_GET['page'];
        }

        if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
            $trans->show = $_GET['nbshow'];
        }

        if ( isset($_GET['tri']) ) {
            $trans->orderby = $_GET['tri'];
        }

        if ( isset($_GET['clear_filter']) ) {
            $trans->reinit();
        } else {
            if ( isset($_GET['end_date_filter']) || isset($_GET['start_date_filter']) ) {
                try {
                    if ( isset($_GET['start_date_filter']) ) {
                        $field = _T("start date filter");
                        $trans->start_date_filter = $_GET['start_date_filter'];
                    }
                    if ( isset($_GET['end_date_filter']) ) {
                        $field = _T("end date filter");
                        $trans->end_date_filter = $_GET['end_date_filter'];
                    }
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
                }
            }
        }*/
        /*if ( ($this->login->isAdmin() || $this->login->isStaff()) && isset($_GET['id_adh']) && $_GET['id_adh'] != '' ) {
            if ( $_GET['id_adh'] == 'all' ) {
                $trans->filtre_cotis_adh = null;
            } else {
                $trans->filtre_cotis_adh = $_GET['id_adh'];
            }
        }
        if ( $this->login->isAdmin() || $this->login->isStaff() ) {
            $trans_id = get_numeric_form_value('sup', '');
            if ($trans_id != '') {
                $trans->removeTransactions($trans_id);
            }
        }*/

        $this->session->transactions = $trans;
        $list_trans = $trans->getTransactionsList(true);

        //assign pagination variables to the template and add pagination links
        $trans->setSmartyPagination($this->router, $this->view->getSmarty());

        /*if ( $trans->filtre_cotis_adh != null ) {
            $member = new Galette\Entity\Adherent();
            $member->load($trans->filtre_cotis_adh);
            $tpl->assign('member', $member);
        }*/

        // display page
        $this->view->render(
            $response,
            'gestion_transactions.tpl',
            array(
                'page_title'            => _T("Transactions management"),
                'require_dialog'        => true,
                'require_calendar'      => true,
                'list_trans'            => $list_trans,
                'transactions'          => $trans,
                'nb_transactions'       => $trans->getCount(),
                'mode'                  => 'std'
            )
        );
        return $response;
    }
)->setName('transactions')->add($authenticate);

$app->post(
    '/{type:contributions|transactions}/filter',
    function ($request, $response, $args) {
        $type = $args['type'];
        $post = $request->getParsedBody();

        if ($this->session->$type !== null) {
            $contribs = $this->session->$type;
        } else {
            $contribs = new Contributions();
        }

        /*if ( $ajax === true ) {
            $contribs->filtre_transactions = true;
            if ( isset($_POST['max_amount']) ) {
                $contribs->max_amount = (int)$_POST['max_amount'];
            } else if ( $_GET['max_amount'] ) {
                $contribs->max_amount = (int)$_GET['max_amount'];
            }
        } else {
            $contribs->max_amount = null;
        }*/
        $contribs->max_amount = null;

        if ((isset($post['nbshow']) && is_numeric($post['nbshow']))
        ) {
            $contribs->show = $post['nbshow'];
        }

        if (isset($post['clear_filter'])) {
            $contribs->reinit();
        } else {
            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                try {
                    if (isset($post['start_date_filter'])) {
                        $field = _T("start date filter");
                        $contribs->start_date_filter = $post['start_date_filter'];
                    }
                    if (isset($post['end_date_filter'])) {
                        $field = _T("end date filter");
                        $contribs->end_date_filter = $post['end_date_filter'];
                    }
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
                }
            }

            if (isset($post['payment_type_filter'])) {
                $ptf = (int)$post['payment_type_filter'];
                if ($ptf == Contribution::PAYMENT_OTHER
                    || $ptf == Contribution::PAYMENT_CASH
                    || $ptf == Contribution::PAYMENT_CREDITCARD
                    || $ptf == Contribution::PAYMENT_CHECK
                    || $ptf == Contribution::PAYMENT_TRANSFER
                    || $ptf == Contribution::PAYMENT_PAYPAL
                ) {
                    $contribs->payment_type_filter = $ptf;
                } elseif ($ptf == -1) {
                    $contribs->payment_type_filter = null;
                } else {
                    $error_detected[] = _T("- Unknown payment type!");
                }
            }
        }

        /*$id = $request->get('id');
        if (($this->login->isAdmin() || $this->login->isStaff())
            && isset($id) && $id != ''
        ) {
            if ($id == 'all') {
                $contribs->filtre_cotis_adh = null;
            } else {
                $contribs->filtre_cotis_adh = $id;
            }
        }*/

        /*if ( $this->login->isAdmin() || $this->login->isStaff() ) {
            //delete contributions
            if (isset($_GET['sup']) || isset($_POST['delete'])) {
                if ( isset($_GET['sup']) ) {
                    $contribs->removeContributions($_GET['sup']);
                } else if ( isset($_POST['contrib_sel']) ) {
                    $contribs->removeContributions($_POST['contrib_sel']);
                }
            }
        }*/

        $this->session->$type = $contribs;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor($type));
    }
)->setName(
    'payments_filter'
)->add($authenticate);

$app->get(
    '/transactions/{action:add|edit}[/{id:\d+}]',
    function ($request, $response, $args) {
        $trans = null;
        $dyn_fields = null;
        if ($this->session->transaction !== null) {
            $trans = $this->session->transaction['transaction'];
            $dyn_fields = $this->session->transaction['dyn_fields'];
            $this->session->transaction = null;
        } else {
            $trans = new Transaction($this->zdb);
            //TODO: dynamic fields should be handled by Transaction object
            $dyn_fields = new DynamicFields();
        }

        $action = $args['action'];
        $trans_id = null;
        if (isset($args['id'])) {
            $trans_id = $args['id'];
        }

        if ($action === 'edit' && $trans_id === null) {
            throw new \RuntimeException(
                _T("Transaction ID cannot ben null calling edit route!")
            );
        } elseif ($action === 'add' && $trans_id !== null) {
             return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('transaction', ['action' => 'add']));
        }

        $transaction['trans_id'] = get_numeric_form_value("trans_id", '');
        $transaction['trans_amount'] = get_numeric_form_value("trans_amount", '');
        $transaction['trans_date'] = get_form_value("trans_date", '');
        $transaction['trans_desc'] = get_form_value("trans_desc", '');
        $transaction['id_adh'] = get_numeric_form_value("id_adh", '');

        // flagging required fields
        $required = array(
            'trans_amount'  =>  1,
            'trans_date'    =>  1,
            'trans_desc'    =>  1,
            'id_adh'        =>  1
        );
        $disabled = array();

        /*if (isset($_GET['detach'])) {
            if (!Contribution::unsetTransactionPart($trans_id, $_GET['detach'])) {
                $error_detected[] = _T("Unable to detach contribution from transaction");
            } else {
                $success_detected[] = _T("Contribution has been successfully detached from current transaction");
            }
        }*/

        /*if (isset($_GET['cid']) && $_GET['cid'] != null) {
            if (!Contribution::setTransactionPart($trans_id, $_GET['cid'])) {
                $error_detected[] = _T("Unable to attach contribution to transaction");
            } else {
                $success_detected[] = _T("Contribution has been successfully attached to current transaction");
            }
        }*/

        if ($action === 'edit') {
            // initialize transactions structure with database values
            $trans->load($trans_id);
            if ($trans->id == '') {
                //not possible to load transaction, exit
                throw new \RuntimeException('Transaction does not exists!');
            }
        }

        // Validation
        $transaction['dyn'] = array();

        if ($trans->id != '') {
            // dynamic fields
            $transaction['dyn'] = $dyn_fields->getFields(
                'trans',
                $transaction["trans_id"],
                false
            );
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
            'data'              => $transaction, //TODO: remove
            'transaction'       => $trans,
            'require_calendar'  => true
        ];

        if ($trans->id != '') {
            $contribs = new Contributions();
            $params['contribs'] = $contribs->getListFromTransaction($trans->id);
        }

        // members
        $m = new Members();
        $required_fields = array(
            'id_adh',
            'nom_adh',
            'prenom_adh'
        );
        $members = $m->getList(false, $required_fields);
        if (count($members) > 0) {
            foreach ($members as $member) {
                $pk = Adherent::PK;
                $sname = mb_strtoupper($member->nom_adh, 'UTF-8') .
                    ' ' . ucwords(mb_strtolower($member->prenom_adh, 'UTF-8'));
                $adh_options[$member->$pk] = $sname;
            }
            $params['adh_options'] = $adh_options;
        }

        // - declare dynamic fields for display
        $dynamic_fields = $dyn_fields->prepareForDisplay(
            'trans',
            $transaction['dyn'],
            array(),
            1
        );
        $params['dynamic_fields'] = $dynamic_fields;

        // display page
        $this->view->render(
            $response,
            'ajouter_transaction.tpl',
            $params
        );
        return $response;
    }
)->setName('transaction')->add($authenticate);

$app->post(
    '/transactions/{action:add|edit}[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $trans = new Transaction($this->zdb);
        //TODO: dynamic fields should be handled by Transaction object
        $dyn_fields = new DynamicFields();

        $action = $args['action'];
        $trans_id = null;
        if (isset($args['id'])) {
            $trans_id = $args['id'];
        }

        if ($action === 'edit' && $trans_id === null) {
            throw new \RuntimeException(
                _T("Transaction ID cannot ben null calling edit route!")
            );
        } elseif ($action === 'add' && $trans_id !== null) {
            throw new \RuntimeException(
                _T("Transaction ID cannot ben set while adding!")
            );
        }

        $transaction['trans_id'] = $trans_id;
        $transaction['trans_amount'] = $post['trans_amount'];
        $transaction['trans_date'] = $post['trans_date'];
        $transaction['trans_desc'] = $post['trans_desc'];
        $transaction['id_adh'] = $post['id_adh'];

        // flagging required fields
        $required = array(
            'trans_amount'  =>  1,
            'trans_date'    =>  1,
            'trans_desc'    =>  1,
            'id_adh'        =>  1
        );
        $disabled = array();

        if ($action === 'edit') {
            // initialize transactions structure with database values
            $trans->load($trans_id);
            if ($trans->id == '') {
                //not possible to load transaction, exit
                throw new \RuntimeException('Transaction does not exists!');
            }
        }

        // Validation
        $transaction['dyn'] = array();

        // dynamic fields
        $transaction['dyn'] = $dyn_fields->extractPosted(
            $post,
            $_FILES,
            array(),
            $transaction['id_adh']
        );
        $dyn_fields_errors = $dyn_fields->getErrors();
        $error_detected = [];
        if (count($dyn_fields_errors) > 0) {
            $error_detected = array_merge($error_detected, $dyn_fields_errors);
        }
        // regular fields
        $valid = $trans->check($_POST, $required, $disabled);
        if ($valid !== true) {
            $error_detected = array_merge($error_detected, $valid);
        }

        if (count($error_detected) == 0) {
            //all goes well, we can proceed
            $new = false;
            if ($trans->id == '') {
                $new = true;
            }

            $store = $trans->store($this->history);
            if ($store === true) {
                //transaction has been stored :)
                if ($new) {
                    $transaction['trans_id'] = $trans->id;
                }
            } else {
                //something went wrong :'(
                $error_detected[] = _T("An error occured while storing the transaction.");
            }
        }

        if (count($error_detected) == 0) {
            // dynamic fields
            $dyn_fields->setAllFields(
                'trans',
                $transaction['trans_id'],
                $transaction['dyn']
            );

            if ($trans->getMissingAmount() > 0) {
                $rparams = [
                    'action'    => 'add',
                    'trans_id'  => $trans->id
                ];

                if (isset($trans->member)) {
                    $params['id_adh'] = $trans->member;
                }

                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor(
                            'contribution',
                            $rparams
                        )
                    );
            } else {
                //report success
                $this->flash->addMessage(
                    'success_detected',
                    _T("Transaction has been successfully stored")
                );

                //get back to transactions list
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor('transactions')
                    );
            }
        } else {
            //something went wrong.
            //store entity in session
            $this->session->transaction = [
                'transaction'   => $trans,
                'dyn_fields'    => $dyn_fields
            ];

            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }

            //redirect to calling action
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('transaction', $args));
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('transactions'));
    }
)->setName('doEditTransaction')->add($authenticate);
