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
use Galette\Entity\ContributionsTypes;
use Galette\Filters\ContributionsList;

/*$app->get(
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
        }*/

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

        /*$this->session->contributions = $contribs;
        $list_contribs = $contribs->getContributionsList(true);

        //assign pagination variables to the template and add pagination links
        $contribs->setSmartyPagination($this->router, $this->view->getSmarty());*/

        /*if ( $contribs->filtre_cotis_adh != null && !$ajax ) {
            $member = new Adherent($this->zdb);
            $member->load($contribs->filtre_cotis_adh);
            $tpl->assign('member', $member);
        }*/

        // display page
        /*$this->view->render(
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
)->add($authenticate);*/

$app->get(
    '/{type:transactions|contributions}[/{option:page|order|member}/{value:\d+|all}]',
    function ($request, $response, $args = []) {
        $ajax = false;
        if ($request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        $filter_name = 'filter_' . $args['type'];

        if (isset($this->session->$filter_name) && $ajax === false) {
            $filters = $this->session->$filter_name;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($args['type'] . 'List');
            $filters = new $filter_class();
        }

        $max_amount = null;
        if (isset($request->getQueryParams()['max_amount'])) {
            $filters->filtre_transactions = true;
            $filters->max_amount = (int)$request->getQueryParams()['max_amount'];
        } else {
            $filters->filtre_transactions = false;
            $filters->max_amount = null;
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
                case 'member':
                    if (($this->login->isAdmin()
                        || $this->login->isStaff())
                    ) {
                        if ($value == 'all') {
                            $filters->filtre_cotis_adh = null;
                        } else {
                            $filters->filtre_cotis_adh = $value;
                        }
                    }
                    break;
            }
        }

        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            $filters->filtre_cotis_adh = $this->login->id;
        }

        $class = '\\Galette\\Repository\\' . ucwords($args['type']);
        $contrib = new $class($this->zdb, $this->login, $filters);

        //store filters into session
        if ($ajax === false) {
            $this->session->$filter_name = $filters;
        }

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view->getSmarty());

        $tpl_vars = [
            'page_title'        => $args['type'] === 'contributions' ?
                                    _T("Contributions management") :
                                    _T("Transactions management"),
            'require_dialog'    => true,
            'require_calendar'  => true,
            'contribs'          => $contrib,
            'list'              => $contrib->getList(true),
            'nb'                => $contrib->getCount(),
            'filters'           => $filters,
            'mode'              => ($ajax === true ? 'ajax' : 'std')
        ];

        if ($filters->filtre_cotis_adh != null) {
            $member = new Adherent($this->zdb);
            $member->load($filters->filtre_cotis_adh);
            $tpl_vars['member'] = $member;
        }

        // display page
        $this->view->render(
            $response,
            'gestion_' . $args['type'] . '.tpl',
            $tpl_vars
        );
        return $response;
    }
)->setName('contributions')->add($authenticate);

$app->post(
    '/{type:contributions|transactions}/filter',
    function ($request, $response, $args) {
        $type = 'filter_' . $args['type'];
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->$type !== null) {
            $filters = $this->session->$type;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($args['type']) . 'List';
            $filters = new $filter_class();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (isset($post['max_amount'])) {
                $filters->max_amount = null;
            }

            if ((isset($post['nbshow']) && is_numeric($post['nbshow']))
            ) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                try {
                    if (isset($post['start_date_filter'])) {
                        $field = _T("start date filter");
                        $filters->start_date_filter = $post['start_date_filter'];
                    }
                    if (isset($post['end_date_filter'])) {
                        $field = _T("end date filter");
                        $filters->end_date_filter = $post['end_date_filter'];
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
                    $filters->payment_type_filter = $ptf;
                } elseif ($ptf == -1) {
                    $filters->payment_type_filter = null;
                } else {
                    $error_detected[] = _T("- Unknown payment type!");
                }
            }
        }

        $this->session->$type = $filters;

        if (count($error_detected) > 0) {
            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('contributions', ['type' => $args['type']]));
    }
)->setName(
    'payments_filter'
)->add($authenticate);

$app->get(
    '/contributions/{action:add|edit}[/{id:\d+}]',
    function ($request, $response, $args) {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution['contribution'];
            $dyn_fields = $this->session->contribution['dyn_fields'];
            $this->session->contribution = null;
        } else {
            $contrib = new Contribution($this->zdb, $this->login);
            //TODO: dynamic fields should be handled by Contribution object
            $dyn_fields = new DynamicFields();
        }

        $action = $args['action'];
        $id_cotis = null;
        if (isset($args['id'])) {
            $id_cotis = $args['id'];
        }

        if ($action === 'edit' && $id_cotis === null) {
            throw new \RuntimeException(
                _T("Contribution ID cannot ben null calling edit route!")
            );
        } elseif ($action === 'add' && $id_cotis !== null) {
             return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('contribution', ['action' => 'add']));
        }

        //first/second step: select member
        $id_adh = get_numeric_form_value('id_adh', '');
        //first/second step: select contribution type
        $selected_type = get_form_value('id_type_cotis', 1);
        //first/second step: transaction id
        $trans_id = get_numeric_form_value('trans_id', '');
        //mark first step has been passed
        $type_selected = $id_cotis != null || get_form_value('type_selected', 0);

        // flagging required fields for first step only
        $required = array(
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1
        );

        $cotis_extension = 0; // TODO: remove and remplace with $contrib->isCotis()
        $disabled = array();

        if ($type_selected && !($id_adh || $id_cotis)) {
            $error_detected[] = _T("You have to select a member.");
            $type_selected = false;
        } elseif ($id_cotis || $type_selected || $trans_id || $id_adh) {
            if ($id_cotis) {
                $contrib = new Contribution($this->zdb, $this->login, (int)$id_cotis);
                if ($contrib->id == '') {
                    //not possible to load contribution, exit
                    $this->flash->addMessage(
                        'error_detected',
                        str_replace(
                            '%id',
                            $id_cotis,
                            _T("Unable to load contribution #%id!")
                        )
                    );
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('contributions', ['type' => 'contributions']));
                }
            } else {
                $args = array(
                    'type'  => $selected_type,
                    'adh'   => $id_adh
                );
                if ($trans_id != '') {
                    $args['trans'] = $trans_id;
                }
                if ($this->preferences->pref_membership_ext != '') {
                    $args['ext'] = $this->preferences->pref_membership_ext;
                }
                $contrib = new Contribution($this->zdb, $this->login, $args);
                if ($contrib->isTransactionPart()) {
                    $id_adh = $contrib->member;
                    //Should we disable contribution member selection if we're from
                    //a transaction? In most cases, it would be OK I guess, but I'm
                    //very unsure
                    //$disabled['id_adh'] = ' disabled="disabled"';
                }
            }

            //second step only: first step, and all the rest
            // flagging required fields for second step
            $second_required = array(
                'montant_cotis'     => 1,
                'date_debut_cotis'  => 1,
                'date_fin_cotis'    => $contrib->isCotis(),
            );
            $required = $required + $second_required;
        }

        // Validation
        $contribution['dyn'] = array();

        if (!is_int($contrib->id)) {
            // initialiser la structure contribution à vide (nouvelle contribution)
            $contribution['duree_mois_cotis'] = $this->preferences->pref_membership_ext;
        } else {
            // dynamic fields
            $contribution['dyn'] = $dyn_fields->getFields(
                'contrib',
                $id_cotis,
                false
            );
        }

        // template variable declaration
        $title = _T("Contribution card");
        if ($contrib->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        $params = [
            'page_title'        => $title,
            'required'          => $required,
            'disabled'          => $disabled,
            'data'              => $contribution, //TODO: remove
            'contribution'      => $contrib,
            'type_selected'     => $type_selected,
            'adh_selected'      => $id_adh,
            'require_calendar'  => true
        ];

        if (isset($head_redirect)) {
            $params['head_redirect'] = $head_redirect;
        }

        // contribution types
        $ct = new ContributionsTypes($this->zdb);
        $type_cotis_options = $ct->getList(
            ($type_selected == 1 && $id_adh != '') ? $contrib->isCotis() : null
        );
        $params['type_cotis_options'] = $type_cotis_options;

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

        $params['pref_membership_ext'] = $cotis_extension ?
            $this->preferences->pref_membership_ext :
            '';  //TODO: remove and replace with $contrib specific property

        // - declare dynamic fields for display
        $dynamic_fields = $dyn_fields->prepareForDisplay(
            'contrib',
            $contribution['dyn'],
            array(),
            1
        );
        $params['dynamic_fields'] = $dynamic_fields;

        // display page
        $this->view->render(
            $response,
            'ajouter_contribution.tpl',
            $params
        );
        return $response;
    }
)->setName('contribution')->add($authenticate);

$app->post(
    '/contributions/{action:add|edit}[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($post['id_adh'] || $post['id_cotis']) {
            $error_detected[] = _T("You have to select a member.");
        } else {
            /*// dynamic fields
            $contribution['dyn'] = $dyn_fields->extractPosted(
                $_POST,
                $_FILES,
                array(),
                $id_adh
            );
            $dyn_fields_errors = $dyn_fields->getErrors();
            if (count($dyn_fields_errors) > 0) {
                $error_detected = array_merge($error_detected, $dyn_fields_errors);
            }
            // regular fields
            $valid = $contrib->check($_POST, $required, $disabled);
            if ($valid !== true) {
                $error_detected = array_merge($error_detected, $valid);
            }

            if (count($error_detected) == 0) {
                //all goes well, we can proceed
                if ($contrib->isCotis()) {
                    // Check that membership fees does not overlap
                    $overlap = $contrib->checkOverlap();
                    if ($overlap !== true) {
                        if ($overlap === false) {
                            $error_detected[] = _T("An error occured checking overlaping fees :(");
                        } else {
                            //method directly return erro message
                            $error_detected[] = $overlap;
                        }
                    } else {

                    }
                }
                $new = false;
                if ($contrib->id == '') {
                    $new = true;
                }

                if (count($error_detected) == 0) {
                    $store = $contrib->store();
                    if ($store === true) {
                        //contribution has been stored :)
                        if ($new) {
                            //if an external script has been configured, we call it
                            if ($this->preferences->pref_new_contrib_script) {
                                $es = new Galette\IO\ExternalScript($this->preferences);
                                $res = $contrib->executePostScript($es);

                                if ($res !== true) {
                                    //send admin a mail with all details
                                    if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                                        $mail = new GaletteMail();
                                        $mail->setSubject(
                                            _T("Post contribution script failed")
                                        );
                                        ** TODO: only super-admin is contacted here. We should send
                                        *  a message to all admins, or propose them a chekbox if
                                        *  they don't want to get bored
                                        *
                                        $mail->setRecipients(
                                            array(
                                                $this->preferences->pref_email_newadh => str_replace(
                                                    '%asso',
                                                    $this->preferences->pref_name,
                                                    _T("%asso Galette's admin")
                                                )
                                            )
                                        );

                                        $message = _T("The configured post contribution script has failed.");
                                        $message .= "\n" . _T("You can find contribution information and script output below.");
                                        $message .= "\n\n";
                                        $message .= $res;

                                        $mail->setMessage($message);
                                        $sent = $mail->send();

                                        if (!$sent) {
                                            $txt = preg_replace(
                                                array('/%name/', '/%email/'),
                                                array($adh->sname, $adh->email),
                                                _T("A problem happened while sending to admin post contribution notification for user %name (%email) contribution")
                                            );
                                            $hist->add($txt);
                                            $error_detected[] = $txt;
                                            //Mails are disabled... We log (not safe, but)...
                                            Analog::log(
                                                'Post contribution script has failed. Here was the data: ' .
                                                "\n" . print_r($res, true),
                                                Analog::ERROR
                                            );
                                        }
                                    } else {
                                        //Mails are disabled... We log (not safe, but)...
                                        Analog::log(
                                            'Post contribution script has failed. Here was the data: ' .
                                            "\n" . print_r($res, true),
                                            Analog::ERROR
                                        );
                                    }
                                }
                            }
                        }
                    } else {
                        //something went wrong :'(
                        $error_detected[] = _T("An error occured while storing the contribution.");
                    }
                }
            }

            if (count($error_detected) == 0) {
                $dyn_fields->setAllFields(
                    'contrib',
                    $contrib->id,
                    $contribution['dyn']
                );

                // Get member informations
                $adh = new Adherent($this->zdb);
                $adh->load($contrib->member);

                if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                    $texts = new Texts(
                        $texts_fields,
                        $this->preferences,
                        array(
                            'name_adh'          => custom_html_entity_decode($adh->sname),
                            'firstname_adh'     => custom_html_entity_decode($adh->surname),
                            'lastname_adh'      => custom_html_entity_decode($adh->name),
                            'mail_adh'          => custom_html_entity_decode($adh->email),
                            'login_adh'         => custom_html_entity_decode($adh->login),
                            'deadline'          => custom_html_entity_decode($contrib->end_date),
                            'contrib_info'      => custom_html_entity_decode($contrib->info),
                            'contrib_amount'    => custom_html_entity_decode($contrib->amount),
                            'contrib_type'      => custom_html_entity_decode($contrib->type->libelle)
                        )
                    );
                    if ($new && isset($_POST['mail_confirm'])
                        && $_POST['mail_confirm'] == '1'
                    ) {
                        if (GaletteMail::isValidEmail($adh->email)) {
                            $text = 'contrib';
                            if (!$contrib->isCotis()) {
                                $text = 'donation';
                            }
                            $mtxt = $texts->getTexts($text, $adh->language);

                            $mail = new GaletteMail();
                            $mail->setSubject($texts->getSubject());
                            $mail->setRecipients(
                                array(
                                    $adh->email => $adh->sname
                                )
                            );

                            $mail->setMessage($texts->getBody());
                            $sent = $mail->send();

                            if ($sent) {
                                $hist->add(
                                    preg_replace(
                                        array('/%name/', '/%email/'),
                                        array($adh->sname, $adh->email),
                                        _T("Mail sent to user %name (%email)")
                                    )
                                );
                            } else {
                                $txt = preg_replace(
                                    array('/%name/', '/%email/'),
                                    array($adh->sname, $adh->email),
                                    _T("A problem happened while sending contribution receipt to user %name (%email)")
                                );
                                $hist->add($txt);
                                $error_detected[] = $txt;
                            }
                        } else {
                            $txt = preg_replace(
                                array('/%name/', '/%email/'),
                                array($adh->sname, $adh->email),
                                _T("Trying to send a mail to a member (%name) with an invalid address: %email")
                            );
                            $hist->add($txt);
                            $warning_detected[] = $txt;
                        }
                    }

                    // Sent email to admin if pref checked
                    if ($new && $this->preferences->pref_bool_mailadh) {
                        // Get email text in database
                        $text = 'newcont';
                        if (!$contrib->isCotis()) {
                            $text = 'newdonation';
                        }
                        $mtxt = $texts->getTexts($text, $this->preferences->pref_lang);

                        $mail = new GaletteMail();
                        $mail->setSubject($texts->getSubject());
                        ** TODO: only super-admin is contacted here. We should send
                        *  a message to all admins, or propose them a chekbox if
                        *  they don't want to get bored
                        *
                        $mail->setRecipients(
                            array(
                                $this->preferences->pref_email_newadh => str_replace(
                                    '%asso',
                                    $this->preferences->pref_name,
                                    _T("%asso Galette's admin")
                                )
                            )
                        );

                        $mail->setMessage($texts->getBody());
                        $sent = $mail->send();

                        if ($sent) {
                            $hist->add(
                                preg_replace(
                                    array('/%name/', '/%email/'),
                                    array($adh->sname, $adh->email),
                                    _T("Mail sent to admin for user %name (%email)")
                                )
                            );
                        } else {
                            $txt = preg_replace(
                                array('/%name/', '/%email/'),
                                array($adh->sname, $adh->email),
                                _T("A problem happened while sending to admin notification for user %name (%email) contribution")
                            );
                            $hist->add($txt);
                            $error_detected[] = $txt;
                        }
                    }
                }

                if (count($error_detected) == 0) {
                    if ($contrib->isTransactionPart()
                        && $contrib->transaction->getMissingAmount() > 0
                    ) {
                        $url = 'ajouter_contribution.php?trans_id=' .
                            $contrib->transaction->id . '&id_adh=' .
                            $contrib->member;
                    } else {
                        $url = 'gestion_contributions.php?id_adh=' . $contrib->member;
                    }
                    if (count($warning_detected) == 0) {
                        header('location: ' . $url);
                        die();
                    } else {
                        $head_redirect = array(
                            'timeout'   => 30,
                            'url'       => $url
                        );
                    }
                }
            }

            * TODO: remove *
            if (!isset($contribution['duree_mois_cotis'])
                || $contribution['duree_mois_cotis'] == ''
            ) {
                // On error restore entered value or default to display the form again
                if (isset($_POST['duree_mois_cotis'])
                    && $_POST['duree_mois_cotis'] != ''
                ) {
                    $contribution['duree_mois_cotis'] = $_POST['duree_mois_cotis'];
                } else {
                    $contribution['duree_mois_cotis'] = $this->preferences->pref_membership_ext;
                }
            }*/
        }

        if (count($error_detected) > 0) {
            //something went wrong.
            //store entity in session
            $this->session->contribution = [
                'contribution'  => $contrib,
                'dyn_fields'    => $dyn_fields
            ];

            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        //redirect to calling action
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('contribution', $args));
    }
)->setName('contribution')->add($authenticate);

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

        $transaction['trans_id'] = $trans_id;
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
            'require_calendar'  => true,
            'require_dialog'    => true
        ];

        if ($trans->id != '') {
            $contribs = new Contributions($this->zdb, $this->login);
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

$app->get(
    '/transactions/{id}/attach/{cid}',
    function ($request, $response, $args) {
        if (!Contribution::setTransactionPart($this->zdb, $args['id'], $args['cid'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Unable to attach contribution to transaction")
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                _T("Contribution has been successfully attached to current transaction")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('transaction', ['action' => 'edit', 'id' => $args['id']]));
    }
)->setName('attach_contribution')->add($authenticate);

$app->get(
    '/transactions/{id}/detach/{cid}',
    function ($request, $response, $args) {
        if (!Contribution::unsetTransactionPart($this->zdb, $this->login, $args['id'], $args['cid'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Unable to detach contribution from transaction")
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                _T("Contribution has been successfully detached from current transaction")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('transaction', ['action' => 'edit', 'id' => $args['id']]));
    }
)->setName('detach_contribution')->add($authenticate);

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
            ->withHeader('Location', $this->router->pathFor('contributions', ['type' => 'transactions']));
    }
)->setName('doEditTransaction')->add($authenticate);
