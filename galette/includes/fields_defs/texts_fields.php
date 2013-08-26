<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Texts table fields declarations
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  Functions
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.7.4dev - 2013-02-08
 */


$texts_fields = array(
    array(
        'tid'       => 1,
        'tref'      => 'sub',
        'tsubject'  => '[{ASSO_NAME}] Your identifiers',
        'tbody'     => "Hello,\r\n\r\nYou've just been subscribed on the members management system of {ASSO_NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
        'tlang'     => 'en_US',
        'tcomment'  => 'New user registration'
    ),
    array(
        'tid'       => 2,
        'tref'      => 'sub',
        'tsubject'  => '[{ASSO_NAME}] Votre adhésion',
        'tbody'     => "Bonjour,\r\n\r\nVous venez d'adhérer à {ASSO_NAME}.\r\n\r\nVous pouvez désormais accéder à vos coordonnées et souscriptions en vous connectant à l'adresse suivante :\r\n{LOGIN_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nMot de passe : {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Nouvelle adhésion'
    ),

    array(
        'tid'       => 4,
        'tref'      => 'pwd',
        'tsubject'  => '[{ASSO_NAME}] Your identifiers',
        'tbody'     => "Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
        'tlang'     => 'en_US',
        'tcomment'  => 'Lost password email'
    ),
    array(
        'tid'       => 5,
        'tref'      => 'pwd',
        'tsubject'  => '[{ASSO_NAME}] Vos Identifiants',
        'tbody'     => "Bonjour,\r\n\r\nQuelqu'un (probablement vous) a demandé la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe :\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nLe lien ci-dessus sera valide jusqu'au {LINK_VALIDITY}.\r\n\r\nA Bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Récupération du mot de passe'
    ),

    array(
        'tid'       => 7,
        'tref'      => 'contrib',
        'tsubject'  => '[{ASSO_NAME}] Your contribution',
        'tbody'     => "Hello,\r\n\r\nYour contribution has successfully been taken into account by {ASSO_NAME}.\r\n\r\nIt is valid until {DEADLINE}.\r\n\r\nYou can now login and browse or modify your personnal data using your galette identifiers at this address:\r\n{LOGIN_URI}.\r\n\r\n{CONTRIB_INFO}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
        'tlang'     => 'en_US',
        'tcomment'  => 'Receipt send for new contribution'
    ),
    array(
        'tid'       => 8,
        'tref'      => 'contrib',
        'tsubject'  => '[{ASSO_NAME}] Votre cotisation',
        'tbody'     => "Bonjour,\r\n\r\nVotre cotisation a été enregistrée et validée par l'association {ASSO_NAME}.\r\n\r\nElle est valable jusqu'au {DEADLINE}\r\n\r\nVous pouvez désormais accéder à vos données personnelles à l'aide de vos identifiants galette à l'adresse suivante :\r\n{LOGIN_URI}.\r\n\r\n{CONTRIB_INFO}\r\n\r\nA Bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Accusé de réception de cotisation'
    ),

    array(
        'tid'       => 10,
        'tref'      => 'newadh',
        'tsubject'  => '[{ASSO_NAME}] New registration from {NAME_ADH}',
        'tbody'     => "Hello dear Administrator,\r\n\r\nA new member has been registered with the following informations:\r\n* Name: {NAME_ADH}\r\n* Login: {LOGIN}\r\n* E-mail: {MAIL_ADH}\r\n\r\nYours sincerly,\r\nGalette",
        'tlang'     => 'en_US',
        'tcomment'  => 'New user registration (sent to admin)'
    ),
    array(
        'tid'       => 11,
        'tref'      => 'newadh',
        'tsubject'  => '[{ASSO_NAME}] Nouvelle inscription de {NAME_ADH}',
        'tbody'     => "Bonjour cher Administrateur,\r\n\r\nUn nouveau membre a été enregistré avec les informations suivantes :\r\n* Nom : {NAME_ADH}\r\n* Login : {LOGIN}\r\n* Courriel : {MAIL_ADH}\r\n\r\nBien sincèrement,\r\nGalette",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Nouvelle adhésion (envoyée a l\'admin)'
    ),

    array(
        'tid'       => 13,
        'tref'      => 'newcont',
        'tsubject'  => '[{ASSO_NAME}] New contribution for {NAME_ADH}',
        'tbody'     => "Hello dear Administrator,\r\n\r\nA contribution from {NAME_ADH} has been registered (new deadline: {DEADLINE})\r\n{CONTRIB_INFO}\r\n\r\nYours sincerly,\r\nGalette",
        'tlang'     => 'en_US',
        'tcomment'  => 'New contribution (sent to admin)'
    ),
    array(
        'tid'       => 14,
        'tref'      => 'newcont',
        'tsubject'  => '[{ASSO_NAME}] Nouvelle contribution de {NAME_ADH}',
        'tbody'     => "Bonjour cher Administrateur,\r\n\r\nUne contribution de {NAME_ADH} a été enregistrée (nouvelle échéance: {DEADLINE})\r\n{CONTRIB_INFO}\r\n\r\nBien sincèrement,\r\nGalette",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Nouvelle contribution (envoyée a l\'admin)'
    ),

    array(
        'tid'       => 16,
        'tref'      => 'newselfadh',
        'tsubject'  => '[{ASSO_NAME}] New self registration from {NAME_ADH}',
        'tbody'     => "Hello dear Administrator,\r\n\r\nA new member has self registred on line with the following informations:\r\n* Name: {NAME_ADH}\r\n* Login: {LOGIN}\r\n* E-mail: {MAIL_ADH}\r\n\r\nYours sincerly,\r\nGalette",
        'tlang'     => 'en_US',
        'tcomment'  => 'New self registration (sent to admin)'
    ),
    array(
        'tid'       => 17,
        'tref'      => 'newselfadh',
        'tsubject'  => '[{ASSO_NAME}] Nouvelle auto inscription de {NAME_ADH}',
        'tbody'     => "Bonjour cher Administrateur,\r\n\r\nUn nouvel adhérent s'est auto inscrit en ligne avec les informations suivantes :\r\n* Nom : {NAME_ADH}\r\n* Login : {LOGIN}\r\n* Courriel : {MAIL_ADH}\r\n\r\nBien sincèrement,\r\nGalette",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Nouvelle auto-inscription (envoyée a l\'admin)'
    ),

    array(
        'tid'       => 19,
        'tref'      => 'accountedited',
        'tsubject'  => '[{ASSO_NAME}] Your account has been modified',
        'tbody'     => "Hello!\r\n\r\nYour account on {ASSO_NAME} (with the login '{LOGIN}') has been modified by an administrator or a staff member.\r\n\r\nYou can log into {LOGIN_URI} to review modifications and/or change it.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
        'tlang'     => 'en_US',
        'tcomment'  => 'Informs user that his account has been modified'
    ),
    array(
        'tid'       => 20,
        'tref'      => 'accountedited',
        'tsubject'  => '[{ASSO_NAME}] Votre compte a été modifié',
        'tbody'     => "Bonjour !\r\n\r\nVotre compte chez {ASSO_NAME} (avec l'identifiant '{LOGIN}') a été modifié par un administrateur ou un membre du bureau.\r\n\r\nVous pouvez vous connecter à l'adresse {LOGIN_URI} pour vérifier ces informations ou les modifier.\r\n\r\nÀ bientôt !\r\n\r\n(ce courriel est un envoi automatique)",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Informe l\'utilisateur que son compte a été modifié'
    ),

    array(
        'tid'       => 22,
        'tref'      => 'impendingduedate',
        'tsubject'  => '[{ASSO_NAME}] Your membership is about to expire',
        'tbody'     => "Hello,\r\n\r\nYour {ASSO_NAME} membership is about to expire in {DAYS_REMAINING} days.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
        'tlang'     => 'en_US',
        'tcomment'  => 'Impending due date'
    ),
    array(
        'tid'       => 23,
        'tref'      => 'impendingduedate',
        'tsubject'  => '[{ASSO_NAME}] Votre adhésion arrive à terme',
        'tbody'     => "Bonjour,\r\n\r\nVotre adhésion à {ASSO_NAME} arrive à son terme dans {DAYS_REMAINING} jours.\r\n\r\nA bientôt!\r\n\r\n(ce courriel est un envoi automatique)",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Échéance proche'
    ),

    array(
        'tid'       => 25,
        'tref'      => 'lateduedate',
        'tsubject'  => '[{ASSO_NAME}] Your membership has expired',
        'tbody'     => "Hello,\r\n\r\nYour {ASSO_NAME} membership has expired for {DAYS_EXPIRED} days.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
        'tlang'     => 'en_US',
        'tcomment'  => 'Late due date'
    ),
    array(
        'tid'       => 26,
        'tref'      => 'lateduedate',
        'tsubject'  => '[{ASSO_NAME}] Votre adhésion a expiré',
        'tbody'     => "Bonjour,\r\n\r\nVotre adhésion à {ASSO_NAME} a expiré depuis {DAYS_EXPIRED} jours.\r\n\r\nA bientôt!\r\n\r\n(ce courriel est un envoi automatique)",
        'tlang'     => 'fr_FR',
        'tcomment'  => 'Échéance dépassée'
    )
);
