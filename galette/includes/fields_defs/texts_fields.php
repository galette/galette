<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Texts table fields declarations
 *
 * PHP version 5
 *
 * Copyright © 2013-2020 The Galette Team
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
 * @copyright 2013-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.7.4dev - 2013-02-08
 */

$texts_fields = array(
    array(
        'tref'      => 'sub',
        'tsubject'  => _T('[{ASSO_NAME}] Your identifiers'),
        'tbody'     => _T("Hello,\r\n\r\nYou've just been subscribed on the members management system of {ASSO_NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New user registration')
    ),

    array(
        'tref'      => 'pwd',
        'tsubject'  => _T('[{ASSO_NAME}] Your identifiers'),
        'tbody'     => _T("Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Lost password email')
    ),

    array(
        'tref'      => 'contrib',
        'tsubject'  => _T('[{ASSO_NAME}] Your contribution'),
        'tbody'     => _T("Hello,\r\n\r\nYour contribution has successfully been taken into account by {ASSO_NAME}.\r\n\r\nIt is valid until {DEADLINE}.\r\n\r\nYou can now login and browse or modify your personnal data using your galette identifiers at this address:\r\n{LOGIN_URI}.\r\n\r\n{CONTRIB_INFO}\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Receipt send for new contribution')
    ),

    array(
        'tref'      => 'newadh',
        'tsubject'  => _T('[{ASSO_NAME}] New registration from {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,\r\n\r\nA new member has been registered with the following information:\r\n* Name: {NAME_ADH}\r\n* Login: {LOGIN}\r\n* E-mail: {MAIL_ADH}\r\n\r\nYours sincerly,\r\nGalette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New user registration (sent to admin)')
    ),

    array(
        'tref'      => 'newcont',
        'tsubject'  => _T('[{ASSO_NAME}] New contribution for {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,\r\n\r\nA contribution from {NAME_ADH} has been registered (new deadline: {DEADLINE})\r\n{CONTRIB_INFO}\r\n\r\nYours sincerly,\r\nGalette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New contribution (sent to admin)')
    ),

    array(
        'tref'      => 'newselfadh',
        'tsubject'  => _T('[{ASSO_NAME}] New self registration from {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,\r\n\r\nA new member has self registred on line with the following information:\r\n* Name: {NAME_ADH}\r\n* Login: {LOGIN}\r\n* E-mail: {MAIL_ADH}\r\n\r\nYours sincerly,\r\nGalette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New self registration (sent to admin)')
    ),

    array(
        'tref'      => 'accountedited',
        'tsubject'  => _T('[{ASSO_NAME}] Your account has been modified'),
        'tbody'     => _T("Hello!\r\n\r\nYour account on {ASSO_NAME} (with the login '{LOGIN}') has been modified by an administrator or a staff member.\r\n\r\nYou can log into {LOGIN_URI} to review modifications and/or change it.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Informs user that his account has been modified')
    ),

    array(
        'tref'      => 'impendingduedate',
        'tsubject'  => _T('[{ASSO_NAME}] Your membership is about to expire'),
        'tbody'     => _T("Hello,\r\n\r\nYour {ASSO_NAME} membership is about to expire in {DAYS_REMAINING} days.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Impending due date')
    ),

    array(
        'tref'      => 'lateduedate',
        'tsubject'  => _T('[{ASSO_NAME}] Your membership has expired'),
        'tbody'     => _T("Hello,\r\n\r\nYour {ASSO_NAME} membership has expired for {DAYS_EXPIRED} days.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Late due date')
    ),

    array(
        'tref'      => 'donation',
        'tsubject'  => _T('[{ASSO_NAME}] Your donation'),
        'tbody'     => _T("Hello,\r\n\r\nYour donation to {ASSO_NAME} has successfully been stored.\r\n\r\n{CONTRIB_INFO}\r\n\r\nThank you!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Receipt send for new donations')
    ),

    array(
        'tref'      => 'newdonation',
        'tsubject'  => _T('[{ASSO_NAME}] New donation for {NAME_ADH}'),
        'tbody'     => _T("Hello dear Administrator,\r\n\r\nA donation from {NAME_ADH} has been registered\r\n{CONTRIB_INFO}\r\n\r\nYours sincerly,\r\nGalette"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('New donation (sent to admin)')
    ),

    array(
        'tref'      => 'admaccountedited',
        'tsubject'  => _T('[{ASSO_NAME}] Account {NAME_ADH} has been modified'),
        'tbody'     => _T("Hello!\r\n\r\n{NAME_ADH} has modified his/her account.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)"),
        'tlang'     => 'en_US',
        'tcomment'  => _T('Informs admin a member edit his information')
    ),
);
