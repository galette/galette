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

$app->get(
    '/contributions(/:id)(/:option/:value)',
    $authenticate,
    function ($id = null, $option = null, $value = null) use ($app, $login, &$session) {
        if (isset($session['contributions'])) {
            $contribs = unserialize($session['contributions']);
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

        $id = $app->request()->get('id');
        if ( ($login->isAdmin() || $login->isStaff())
            && isset($id) && $id != ''
        ) {
            if ( $id == 'all' ) {
                $contribs->filtre_cotis_adh = null;
            } else {
                $contribs->filtre_cotis_adh = $id;
            }
        }

        /*if ( $login->isAdmin() || $login->isStaff() ) {
            //delete contributions
            if (isset($_GET['sup']) || isset($_POST['delete'])) {
                if ( isset($_GET['sup']) ) {
                    $contribs->removeContributions($_GET['sup']);
                } else if ( isset($_POST['contrib_sel']) ) {
                    $contribs->removeContributions($_POST['contrib_sel']);
                }
            }
        }*/

        $session['contributions'] = serialize($contribs);
        $list_contribs = $contribs->getContributionsList(true);

        $view = $app->view();

        //assign pagination variables to the template and add pagination links
        $contribs->setSmartyPagination($app, $view);

        /*if ( $contribs->filtre_cotis_adh != null && !$ajax ) {
            $member = new Adherent();
            $member->load($contribs->filtre_cotis_adh);
            $tpl->assign('member', $member);
        }*/

        $app->render(
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
    }
)->setName(
    'contributions'
)/*->conditions(
    array(
        'option'    => '(page|order)',
        'value'     => '\d+'
    )
)*/;

$app->get(
    '/transactions',
    $authenticate,
    function () use ($app, $login, &$session) {
        if (!$login->isAdmin() && !$login->isStaff()) {
            $id_adh = $login->id;
        } else {
            $id_adh = get_numeric_form_value('id_adh', '');
        }

        $filtre_id_adh = '';

        if ( isset($session['transactions']) ) {
            $trans = unserialize($session['transactions']);
        } else {
            $trans = new Galette\Repository\Transactions();
        }

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
        /*if ( ($login->isAdmin() || $login->isStaff()) && isset($_GET['id_adh']) && $_GET['id_adh'] != '' ) {
            if ( $_GET['id_adh'] == 'all' ) {
                $trans->filtre_cotis_adh = null;
            } else {
                $trans->filtre_cotis_adh = $_GET['id_adh'];
            }
        }
        if ( $login->isAdmin() || $login->isStaff() ) {
            $trans_id = get_numeric_form_value('sup', '');
            if ($trans_id != '') {
                $trans->removeTransactions($trans_id);
            }
        }*/

        $session['transactions'] = serialize($trans);
        $list_trans = $trans->getTransactionsList(true);

        $view = $app->view();
        //assign pagination variables to the template and add pagination links
        $trans->setSmartyPagination($app, $view);

        /*if ( $trans->filtre_cotis_adh != null ) {
            $member = new Galette\Entity\Adherent();
            $member->load($trans->filtre_cotis_adh);
            $tpl->assign('member', $member);
        }*/


        $app->render(
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

    }
)->setName('transactions');

$app->post(
    '/:type/filter',
    $authenticate,
    function ($type) use ($app, $login, &$session) {
        $request = $app->request();

        if (isset($session[$type])) {
            $contribs = unserialize($session[$type]);
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

        if (($request->post('nbshow') && is_numeric($request->post('nbshow')))
        ) {
            $contribs->show = $request->post('nbshow');
        }

        if ($request->post('clear_filter')) {
            $contribs->reinit();
        } else {
            if ($request->post('end_date_filter') || $request->post('start_date_filter')) {
                try {
                    if ($request->post('start_date_filter')) {
                        $field = _T("start date filter");
                        $contribs->start_date_filter = $request->post('start_date_filter');
                    }
                    if ($request->post('end_date_filter')) {
                        $field = _T("end date filter");
                        $contribs->end_date_filter = $request->post('end_date_filter');
                    }
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
                }
            }

            if ($request->post('payment_type_filter') !== null) {
                $ptf = (int)$request->post('payment_type_filter');
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

        $id = $request->get('id');
        if (($login->isAdmin() || $login->isStaff())
            && isset($id) && $id != ''
        ) {
            if ($id == 'all') {
                $contribs->filtre_cotis_adh = null;
            } else {
                $contribs->filtre_cotis_adh = $id;
            }
        }

        /*if ( $login->isAdmin() || $login->isStaff() ) {
            //delete contributions
            if (isset($_GET['sup']) || isset($_POST['delete'])) {
                if ( isset($_GET['sup']) ) {
                    $contribs->removeContributions($_GET['sup']);
                } else if ( isset($_POST['contrib_sel']) ) {
                    $contribs->removeContributions($_POST['contrib_sel']);
                }
            }
        }*/

        $session[$type] = serialize($contribs);

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor($type));
    }
)->setName(
    'payments_filter'
)/*->conditions(
    array(
        'type' => '(contributions|transactions)',
    )
)*/;
