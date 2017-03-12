<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions related routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2016 The Galette Team
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
 * @copyright 2014-2016 The Galette Team
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
use Galette\Core\GaletteMail;
use Galette\Entity\Texts;
use Galette\IO\PdfContribution;

$app->get(
    '/{type:' . __('transactions', 'routes') .'|'. __('contributions', 'routes') .
        '}[/{option:' . __('page', 'routes') .'|'. __('order', 'routes') .'|' .
        __('member', 'routes') .'}/{value:\d+|all}]',
    function ($request, $response, $args) {
        $ajax = false;
        if ($request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $ajax = true;
        }
        $get = $request->getQueryParams();

        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        $raw_type = null;
        switch ($args['type']) {
            case __('transactions', 'routes'):
                $raw_type = 'transactions';
                break;
            case __('contributions', 'routes'):
                $raw_type = 'contributions';
                break;
        }

        $filter_name = 'filter_' . $raw_type;

        if (isset($this->session->$filter_name) && $ajax === false) {
            $filters = $this->session->$filter_name;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($raw_type . 'List');
            $filters = new $filter_class();
        }

        //member id
        $id_adh = null;
        if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0) {
            $id_adh = (int)$get[Adherent::PK];
            $filters->filtre_cotis_adh = $id_adh;
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
                case __('page', 'routes'):
                    $filters->current_page = (int)$value;
                    break;
                case __('order', 'routes'):
                    $filters->orderby = $value;
                    break;
                case __('member', 'routes'):
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

        $class = '\\Galette\\Repository\\' . ucwords($raw_type);
        $contrib = new $class($this->zdb, $this->login, $filters);

        //store filters into session
        if ($ajax === false) {
            $this->session->$filter_name = $filters;
        }

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view->getSmarty());

        $tpl_vars = [
            'page_title'        => $raw_type === 'contributions' ?
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
            'gestion_' . $raw_type . '.tpl',
            $tpl_vars
        );
        return $response;
    }
)->setName('contributions')->add($authenticate);

$app->post(
    '/{type:' . __('contributions', 'routes') .'|' . __('transactions', 'routes') .'}' . __('/filter', 'routes'),
    function ($request, $response, $args) {
        $raw_type = null;
        switch ($args['type']) {
            case __('transactions', 'routes'):
                $raw_type = 'transactions';
                break;
            case __('contributions', 'routes'):
                $raw_type = 'contributions';
                break;
        }

        $type = 'filter_' . $raw_type;
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->$type !== null) {
            $filters = $this->session->$type;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($raw_type) . 'List';
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
            ->withHeader('Location', $this->router->pathFor('contributions', ['type' => $raw_type]));
    }
)->setName(
    'payments_filter'
)->add($authenticate);

$app->get(
    __('/contribution', 'routes') .
        '/{type:' . __('fee') . '|' . __('donation') . '}/{action:' .
        __('add') . '|' . __('edit') .'}[/{id:\d+}]',
    function ($request, $response, $args) {
        $action = $args['action'];
        $get = $request->getQueryParams();
        $id_cotis = null;
        if (isset($args['id'])) {
            $id_cotis = $args['id'];
        }

        if ($action === __('edit', 'routes') && $id_cotis === null) {
            throw new \RuntimeException(
                _T("Contribution ID cannot ben null calling edit route!")
            );
        } elseif ($action === __('add', 'routes') && $id_cotis !== null) {
             return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('contribution', ['action' => __('add', 'routes')]));
        }

        // contribution types
        $ct = new ContributionsTypes($this->zdb);
        $contributions_types = $ct->getList($args['type'] === __('fee', 'routes'));

        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution['contribution'];
            $dyn_fields = $this->session->contribution['dyn_fields'];
            $this->session->contribution = null;
        } else {
            if ($args['action'] === __('edit', 'routes')) {
                $contrib = new Contribution($this->zdb, $this->login, (int)$id_cotis);
                $id_adh = $contrib->member;
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
                        ->withHeader('Location', $this->router->pathFor(
                            'contributions',
                            ['type' => __('contributions', 'routes')]
                        ));
                }
            } else {
                $cparams = ['type' => __(array_keys($contributions_types)[0], 'routes')];

                //member id
                $id_adh = null;
                if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0 && $action === __('add', 'routes')) {
                    $id_adh = (int)$get[Adherent::PK];
                    $cparams['adh'] = $id_adh;
                }

                //transaction id
                $trans_id = null;
                if (isset($get[Transaction::PK]) && $get[Transaction::PK] > 0) {
                    $cparams['trans'] = $get[Transaction::PK];
                }

                $contrib = new Contribution(
                    $this->zdb,
                    $this->login,
                    (count($cparams) > 0 ? $cparams : null)
                );

                if ($contrib->isTransactionPart()) {
                    $id_adh = $contrib->member;

                    //check if mmber has children to populate members list
                    $deps = [
                        'picture'   => false,
                        'groups'    => false,
                        'dues'      => false,
                        'parent'    => false,
                        'children'  => true
                    ];
                    $tmember = new Adherent($this->zdb, $id_adh, $deps);
                    $members = [$tmember->id => $tmember->sname];

                    if ($tmember->hasChildren()) {
                        foreach ($tmember->children as $member) {
                            $members[$member->id] = $member->sname;
                        }
                    }
                }
            }

            //TODO: dynamic fields should be handled by Contribution object
            $dyn_fields = new DynamicFields();
        }

        $disabled = array();

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
        $title = null;
        if ($args['type'] === __('fee', 'routes')) {
            $title = _T("Membership fee");
        } else {
            $title = _T("Donation");
        }

        if ($contrib->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        // required fields
        $required = [
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1,
            'date_debut_cotis'  => 1,
            'date_fin_cotis'    => $contrib->isCotis(),
            'montant_cotis'     => $contrib->isCotis() ? 1 : 0
        ];

        $params = [
            'page_title'        => $title,
            'required'          => $required,
            'disabled'          => $disabled,
            'contribution'      => $contrib,
            'adh_selected'      => $id_adh,
            'require_calendar'  => true,
            'type'              => $args['type']
        ];

        // contribution types
        $params['type_cotis_options'] = $contributions_types;

        // members
        if (!isset($members)) {
            $members = [];
            $m = new Members();
            $required_fields = array(
                'id_adh',
                'nom_adh',
                'prenom_adh'
            );
            $list_members = $m->getList(false, $required_fields);

            if (count($list_members) > 0) {
                foreach ($list_members as $member) {
                    $pk = Adherent::PK;
                    $sname = mb_strtoupper($member->nom_adh, 'UTF-8') .
                        ' ' . ucwords(mb_strtolower($member->prenom_adh, 'UTF-8'));
                    $members[$member->$pk] = $sname;
                }
            }
        }

        if (isset($members) && is_array($members)) {
            $params['adh_options'] = $members;
        }

        $ext_membership = '';
        if (isset($contrib) && $contrib->isCotis() || !isset($contrib) && $args['type'] === __('fee', 'routes')) {
            $ext_membership = $this->preferences->pref_membership_ext;
        }
        $params['pref_membership_ext'] = $ext_membership;

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
    __('/contribution', 'routes') .
        '/{type:' . __('fee') . '|' . __('donation') . '}/{action:' .
        __('add') . '|' . __('edit') .'}[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $error_detected = [];
        $warning_detected = [];
        $reditect_url = null;

        $action = $args['action'];
        $id_cotis = null;
        if (isset($args['id'])) {
            $id_cotis = $args['id'];
        }

        $id_adh = $post['id_adh'];

        if ($action === __('edit', 'routes') && $id_cotis === null) {
            throw new \RuntimeException(
                _T("Contribution ID cannot ben null calling edit route!")
            );
        } elseif ($action === __('add', 'routes') && $id_cotis !== null) {
            throw new \RuntimeException(
                _T("Contribution ID must be null calling add route!")
            );
        }

        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution['contribution'];
            $dyn_fields = $this->session->contribution['dyn_fields'];
            $this->session->contribution = null;
        } else {
            if ($id_cotis === null) {
                $contrib = new Contribution($this->zdb, $this->login);
            } else {
                $contrib = new Contribution($this->zdb, $this->login, (int)$id_cotis);
            }
        }

        // dynamic fields
        //TODO: dynamic fields should be handled by Contribution object
        $dyn_fields = new DynamicFields();
        $contribution['dyn'] = $dyn_fields->extractPosted(
            $post,
            $_FILES,
            array(),
            $id_adh
        );
        $dyn_fields_errors = $dyn_fields->getErrors();
        if (count($dyn_fields_errors) > 0) {
            $error_detected = array_merge($error_detected, $dyn_fields_errors);
        }

        // flagging required fields for first step only
        $required = [
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1,
            'montant_cotis'     => 1, //TODO: not always required, see #196
            'date_debut_cotis'  => 1,
            'date_fin_cotis'    => ($args['type'] === __('fee', 'routes'))
        ];
        $disabled = [];

        // regular fields
        $valid = $contrib->check($post, $required, $disabled);
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
                                    /** TODO: only super-admin is contacted here. We should send
                                    *  a message to all admins, or propose them a chekbox if
                                    *  they don't want to get bored
                                    */
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
                                            array($adh->sname, $adh->getEmail()),
                                            _T("A problem happened while sending to admin post contribution notification for user %name (%email) contribution")
                                        );
                                        $this->hist->add($txt);
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
                    $this->texts_fields,
                    $this->preferences,
                    $this->router,
                    array(
                        'name_adh'          => custom_html_entity_decode($adh->sname),
                        'firstname_adh'     => custom_html_entity_decode($adh->surname),
                        'lastname_adh'      => custom_html_entity_decode($adh->name),
                        'mail_adh'          => custom_html_entity_decode($adh->getEmail()),
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
                    if (GaletteMail::isValidEmail($adh->getEmail())) {
                        $text = 'contrib';
                        if (!$contrib->isCotis()) {
                            $text = 'donation';
                        }
                        $mtxt = $texts->getTexts($text, $adh->language);

                        $mail = new GaletteMail();
                        $mail->setSubject($texts->getSubject());
                        $mail->setRecipients(
                            array(
                                $adh->getEmail() => $adh->sname
                            )
                        );

                        $mail->setMessage($texts->getBody());
                        $sent = $mail->send();

                        if ($sent) {
                            $this->hist->add(
                                preg_replace(
                                    array('/%name/', '/%email/'),
                                    array($adh->sname, $adh->getEmail()),
                                    _T("Mail sent to user %name (%email)")
                                )
                            );
                        } else {
                            $txt = preg_replace(
                                array('/%name/', '/%email/'),
                                array($adh->sname, $adh->getEmail()),
                                _T("A problem happened while sending contribution receipt to user %name (%email)")
                            );
                            $this->hist->add($txt);
                            $error_detected[] = $txt;
                        }
                    } else {
                        $txt = preg_replace(
                            array('/%name/', '/%email/'),
                            array($adh->sname, $adh->getEmail()),
                            _T("Trying to send a mail to a member (%name) with an invalid address: %email")
                        );
                        $this->hist->add($txt);
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
                    /** TODO: only super-admin is contacted here. We should send
                    *  a message to all admins, or propose them a chekbox if
                    *  they don't want to get bored
                    */
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
                        $this->hist->add(
                            preg_replace(
                                array('/%name/', '/%email/'),
                                array($adh->sname, $adh->getEmail()),
                                _T("Mail sent to admin for user %name (%email)")
                            )
                        );
                    } else {
                        $txt = preg_replace(
                            array('/%name/', '/%email/'),
                            array($adh->sname, $adh->getEmail()),
                            _T("A problem happened while sending to admin notification for user %name (%email) contribution")
                        );
                        $this->hist->add($txt);
                        $error_detected[] = $txt;
                    }
                }
            }

            if (count($warning_detected) > 0) {
                foreach ($warning_detected as $warning) {
                    $this->flash->addMessage(
                        'warning_detected',
                        $warning
                    );
                }
            }

            if (count($error_detected) == 0) {
                if ($contrib->isTransactionPart()
                    && $contrib->transaction->getMissingAmount() > 0
                ) {
                    $reditect_url = $this->router->pathFor(
                        'contribution',
                        [
                            'type'      => $args['type'],
                            'action'    => __('add', 'routes')
                        ]
                    ) . '?trans_id=' . $contrib->transaction->id . '&id_adh=' . $contrib->member;
                } else {
                    $reditect_url = $this->router->pathFor(
                        'contributions',
                        [
                            'type'      => __('contributions', 'routes')
                        ]
                    ) . '?id_adh=' . $contrib->member;
                }
            }
        }

        /* TODO: remove */
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
        }

        if (count($error_detected) > 0) {
            //something went wrong.
            //store entity in session
            $this->session->contribution = [
                'contribution'  => $contrib,
                'dyn_fields'    => $dyn_fields
            ];
            $reditect_url = $this->router->pathFor('contribution', $args);

            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->session->contribution = null;
            if ($reditect_url === null) {
                $reditect_url = $this->router->pathFor('contributions', ['type' => $args['type']]);
            }
        }

        //redirect to calling action
        return $response
            ->withStatus(301)
            ->withHeader('Location', $reditect_url);
    }
)->setName('contribution')->add($authenticate);

$app->get(
    __('/transaction', 'routes') .
        '/{action:' . __('add') . '|' . __('edit') .'}[/{id:\d+}]',
    function ($request, $response, $args) {
        $trans = null;
        $dyn_fields = null;

        if ($this->session->transaction !== null) {
            $trans = $this->session->transaction['transaction'];
            $dyn_fields = $this->session->transaction['dyn_fields'];
            $this->session->transaction = null;
        } else {
            $trans = new Transaction($this->zdb, $this->login);
            //TODO: dynamic fields should be handled by Transaction object
            $dyn_fields = new DynamicFields();
        }

        $action = $args['action'];
        $trans_id = null;
        if (isset($args['id'])) {
            $trans_id = $args['id'];
        }

        if ($action === __('edit', 'routes') && $trans_id === null) {
            throw new \RuntimeException(
                _T("Transaction ID cannot ben null calling edit route!")
            );
        } elseif ($action === __('add', 'routes') && $trans_id !== null) {
             return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('transaction', ['action' => __('add', 'routes')]));
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

        if ($action === __('edit', 'routes')) {
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
        if ($action === __('edit', 'routes')) {
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
    __('/transaction', 'routes') . '/{id}' . __('/attach', 'routes') . '/{cid}',
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
            ->withHeader('Location', $this->router->pathFor(
                'transaction',
                ['action' => __('edit', 'routes'), 'id' => $args['id']]
            ));
    }
)->setName('attach_contribution')->add($authenticate);

$app->get(
    __('/transaction', 'routes') . '/{id}' . __('/detach', 'routes') . '/{cid}',
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
            ->withHeader('Location', $this->router->pathFor(
                'transaction',
                ['action' => __('edit', 'routes'), 'id' => $args['id']]
            ));
    }
)->setName('detach_contribution')->add($authenticate);

$app->post(
    __('/transaction', 'routes') .
        '/{action:' . __('add') . '|' . __('edit') .'}[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $trans = new Transaction($this->zdb, $this->login);
        //TODO: dynamic fields should be handled by Transaction object
        $dyn_fields = new DynamicFields();

        $action = $args['action'];
        $trans_id = null;
        if (isset($args['id'])) {
            $trans_id = $args['id'];
        }

        if ($action === __('edit', 'routes') && $trans_id === null) {
            throw new \RuntimeException(
                _T("Transaction ID cannot ben null calling edit route!")
            );
        } elseif ($action === __('add', 'routes') && $trans_id !== null) {
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

        if ($action === __('edit', 'routes')) {
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
                    'action'    => __('add', 'routes'),
                    'trans_id'  => $trans->id
                ];

                if (isset($trans->member)) {
                    $rparams['id_adh'] = $trans->member;
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
            ->withHeader('Location', $this->router->pathFor('contributions', ['type' => __('transactions', 'routes')]));
    }
)->setName('doEditTransaction')->add($authenticate);

$app->get(
    '/{type:' . __('contributions', 'routes') .'|' . __('transactions', 'routes') .'}' .
        __('/remove', 'routes') .'/{id:\d+}',
    function ($request, $response, $args) {
        $raw_type = null;
        switch ($args['type']) {
            case __('transactions', 'routes'):
                $raw_type = 'transactions';
                break;
            case __('contributions', 'routes'):
                $raw_type = 'contributions';
                break;
        }

        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('contributions', ['type' => $args['type']])
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => ($raw_type === 'contributions') ? _T('Contributions') : _T('Transactions'),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove %1$s #%2$s'),
                    ($raw_type === 'contributions') ? _T('contribution') : _T('transaction'),
                    $args['id']
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveContribution',
                    ['id' => $args['id'], 'type' => $args['type']]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeContributions')->add($authenticate);

$app->post(
    '/{type:' . __('contributions', 'routes') .'|' . __('transactions', 'routes') .'}' .
        __('/remove', 'routes') .'[/{id}]',
    function ($request, $response, $args) {
        $ids = null;
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        if (isset($post['contrib_sel'])) {
            $ids = $post['contrib_sel'];
        } elseif (isset($post['id'])) {
            $ids = [$post['id']];
        }

        $raw_type = null;
        switch ($args['type']) {
            case __('transactions', 'routes'):
                $raw_type = 'transactions';
                break;
            case __('contributions', 'routes'):
                $raw_type = 'contributions';
                break;
        }

        $uri = isset($post['redirect_uri']) ?
            $post['redirect_uri'] :
            $this->router->pathFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            $class = '\\Galette\Repository\\' . ucwords($raw_type);
            $contribs = new $class($this->zdb, $this->login);
            $rm = $contribs->remove($ids, $this->history);
            if ($rm) {
                $msg = null;
                if ($raw_type === 'contributions') {
                    $msg = _T("Contributions(s) has been removed!");
                } else {
                    $msg = _T("Transactions(s) has been removed!");
                }
                $this->flash->addMessage(
                    'success_detected',
                    $msg
                );
                $success = true;
            } else {
                $msg = null;
                if ($raw_type === 'contributions') {
                    $msg = _T("An error occured trying to remove contributions(s) :(");
                } else {
                    $msg = _T("An error occured trying to remove transaction(s) :(");
                }
                $this->flash->addMessage(
                    'error_detected',
                    $msg
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
)->setName('doRemoveContribution')->add($authenticate);

//Contribution PDF
$app->get(
    __('/contribution', 'routes') . __('/print', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $contribution = new Contribution($this->zdb, $this->login, (int)$args['id']);
        $pdf = new PdfContribution($contribution, $this->zdb, $this->preferences);
        $pdf->download();
    }
)->setName('printContribution')->add($authenticate);
