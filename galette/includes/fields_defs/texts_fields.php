<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Texts table fields declarations
 *
 * PHP version 5
 *
 * Copyright © 2013-2020 The Galette Team
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
 *
 * @category  Functions
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     0.7.4dev - 2013-02-08
 */

$texts_fields = array(
    array(
        'tref'      => 'sub',
        'tsubject'  => _T('[{ASSO_NAME}] Your identifiers'),
        'tbody'     => _T("Hello,{NEWLINE}You've just been subscribed on the members management system of {ASSO_NAME}.{NEWLINE}It is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.{NEWLINE}Please login at this address to set your new password :{BR}{CHG_PWD_URI}{NEWLINE}Username: {LOGIN}{BR}The above link will be valid until {LINK_VALIDITY}.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New user registration')
    ),

    array(
        'tref'      => 'pwd',
        'tsubject'  => _T('[{ASSO_NAME}] Your identifiers'),
        'tbody'     => _T("Hello,{NEWLINE}Someone (probably you) asked to recover your password.{NEWLINE}Please login at this address to set your new password :{BR}{CHG_PWD_URI}{NEWLINE}Username: {LOGIN}{BR}The above link will be valid until {LINK_VALIDITY}.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Lost password email')
    ),

    array(
        'tref'      => 'contrib',
        'tsubject'  => _T('[{ASSO_NAME}] Your contribution'),
        'tbody'     => _T("Hello,{NEWLINE}Your contribution has successfully been taken into account by {ASSO_NAME}.{NEWLINE}It is valid until {DEADLINE}.{NEWLINE}You can now login and browse or modify your personal data using your galette identifiers at this address:{BR}{LOGIN_URI}.{NEWLINE}{CONTRIB_INFO}{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Receipt send for new contribution')
    ),

    array(
        'tref'      => 'newadh',
        'tsubject'  => _T('[{ASSO_NAME}] New registration from {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,{NEWLINE}A new member has been registered with the following information:{BR}* Name: {NAME_ADH}{BR}* Login: {LOGIN}{BR}* E-mail: {MAIL_ADH}{NEWLINE}Yours sincerely,{BR}Galette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New user registration (sent to admin)')
    ),

    array(
        'tref'      => 'newcont',
        'tsubject'  => _T('[{ASSO_NAME}] New contribution for {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,{NEWLINE}A contribution from {NAME_ADH} has been registered (new deadline: {DEADLINE}){BR}{CONTRIB_INFO}{NEWLINE}Yours sincerly,{BR}Galette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New contribution (sent to admin)')
    ),

    array(
        'tref'      => 'newselfadh',
        'tsubject'  => _T('[{ASSO_NAME}] New self registration from {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,{NEWLINE}A new member has self registered on line with the following information:{BR}* Name: {NAME_ADH}{BR}* Login: {LOGIN}{BR}* E-mail: {MAIL_ADH}{NEWLINE}Yours sincerly,{BR}Galette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New self registration (sent to admin)')
    ),

    array(
        'tref'      => 'accountedited',
        'tsubject'  => _T('[{ASSO_NAME}] Your account has been modified'),
        'tbody'     => _T("Hello!{NEWLINE}Your account on {ASSO_NAME} (with the login '{LOGIN}') has been modified by an administrator or a staff member.{NEWLINE}You can log into {LOGIN_URI} to review modifications and/or change it.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Informs user that his account has been modified')
    ),

    array(
        'tref'      => 'impendingduedate',
        'tsubject'  => _T('[{ASSO_NAME}] Your membership is about to expire'),
        'tbody'     => _T("Hello,{NEWLINE}Your {ASSO_NAME} membership is about to expire in {DAYS_REMAINING} days.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Impending due date')
    ),

    array(
        'tref'      => 'lateduedate',
        'tsubject'  => _T('[{ASSO_NAME}] Your membership has expired'),
        'tbody'     => _T("Hello,{NEWLINE}Your {ASSO_NAME} membership has expired for {DAYS_EXPIRED} days.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Late due date')
    ),

    array(
        'tref'      => 'donation',
        'tsubject'  => _T('[{ASSO_NAME}] Your donation'),
        'tbody'     => _T("Hello,{NEWLINE}Your donation to {ASSO_NAME} has successfully been stored.{NEWLINE}{CONTRIB_INFO}{NEWLINE}Thank you!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Receipt send for new donations')
    ),

    array(
        'tref'      => 'newdonation',
        'tsubject'  => _T('[{ASSO_NAME}] New donation for {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,{NEWLINE}A donation from {NAME_ADH} has been registered{BR}{CONTRIB_INFO}{NEWLINE}Yours sincerly,{BR}Galette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New donation (sent to admin)')
    ),

    array(
        'tref'      => 'admaccountedited',
        'tsubject'  => _T('[{ASSO_NAME}] Account {NAME_ADH} has been modified'),
        'tbody'     => _T("Hello!{NEWLINE}{NAME_ADH} has modified his/her account.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Informs admin a member edit his information')
    ),

    array(
        'tref'      => 'pwddisabled',
        'tsubject'  => _T('[{ASSO_NAME}] Account {NAME_ADH} is inactive'),
        'tbody'     => _T("Hello!{NEWLINE}A password recovery request has been made on your account on {ASSO_NAME}, but it is currently inactive and therefore cannot be processed.{NEWLINE}Please contact an administrator or a staff member if you think this is a mistake.{NEWLINE}See you soon!{NEWLINE}(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Lost password email (disabled)')
    ),
);
