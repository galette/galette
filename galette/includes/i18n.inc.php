<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

if (!defined('GALETTE_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/** @var \Galette\Core\I18n $i18n */
$i18n->updateEnv();
global $language;
//FIXME: should be handled differently
$language = $i18n->getLongID(); //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used as global


/**********************************************
* some constant strings found in the database *
**********************************************/
/** TODO: these string should be not be handled here */
//phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used to translate extra strings
$foo = _T("Realization:");
$foo = _T("Graphics:");
$foo = _T("Publisher:");
$foo = _T("President");
$foo = _T("Vice-president");
$foo = _T("Treasurer");
$foo = _T("Vice-treasurer");
$foo = _T("Secretary");
$foo = _T("Vice-secretary");
$foo = _T("Active member");
$foo = _T("Benefactor member");
$foo = _T("Founder member");
$foo = _T("Old-timer");
$foo = _T("Legal entity");
$foo = _T("Non-member");
$foo = _T("Reduced annual contribution");
$foo = _T("Company fee");
$foo = _T("Donation in kind");
$foo = _T("Donation in money");
$foo = _T("Partnership");
$foo = _T("french");
$foo = _T("english");
$foo = _T("spanish");
$foo = _T("annual fee");
$foo = _T("annual fee (to be paid)");
$foo = _T("company fee");
$foo = _T("donation in kind");
$foo = _T("donation in money");
$foo = _T("partnership");
$foo = _T("reduced annual fee");
$foo = _T("Identity");
$foo = _T("Galette-related data");
$foo = _T("Contact information");
$foo = _T("Mr.");
$foo = _T("Mrs.");
$foo = _T("Miss");
$foo = _T("Identity:");
$foo = _T("Galette-related data:");
$foo = _T("Contact information:");
$foo = _T("Society");
$foo = _T('Politeness');
//pdf models
$foo = _T('Main');
$foo = _T("** Galette identifier, if applicable");
$foo = _T("* Only for compagnies");
$foo = _T("Hereby, I agree to comply to %s association statutes and its rules.");
$foo = _T("At ................................................");
$foo = _T("On .......... / .......... / .......... ");
$foo = _T("Username");
$foo = _T("Email address");
$foo = _T("Country");
$foo = _T("City");
$foo = _T("Zip Code");
$foo = _T("First name");
$foo = _T("The minimum contribution for each type of membership are defined on the website of the association. The amount of donations are free to be decided by the generous donor.");
$foo = _T("Required membership:");
$foo = _T("Complete the following form and send it with your funds, in order to complete your subscription.");
$foo = _T('on');
$foo = _T('from');
$foo = _T('to');
$foo = _T('Association');
//phpcs:enable
