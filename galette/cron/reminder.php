<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette cron reminders
 *
 * PHP version 5
 *
 * Copyright © 2013-2016 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-08
 */

use Galette\Entity\Texts;
use Galette\Repository\Members;
use Galette\Repository\Reminders;
use Galette\Filters\MembersList;

/** @ignore */
require_once '../includes/galette.inc.php';

if (!$login->isCron()) {
    die();
}

//FIXME: connect this script to the DI, or instanciate what is required.
$texts = new Texts($texts_fields, $preferences);
$reminders = new Reminders();


$list_reminders = $reminders->getList($zdb, false);
if (count($list_reminders) > 0) {
    foreach ($list_reminders as $reminder) {
        //send reminders by mail
        $sent = $reminder->send($texts, $hist, $zdb);

        if ($sent === true) {
            $success_detected[] = $reminder->getMessage();
        } else {
            $error_detected[] = $reminder->getMessage();
        }
    }

    if (count($error_detected) > 0) {
        array_unshift(
            $error_detected,
            _T("Reminder has not been sent:")
        );
    }

    if (count($success_detected) > 0) {
        array_unshift(
            $success_detected,
            _T("Sent reminders:")
        );
    }
}

//called from a cron. warning and errors has been stored into history
//and probably logged
if (count($error_detected) > 0) {
    //if there are errors, we print them
    echo "\n";
    $count = 0;
    foreach ($error_detected as $e) {
        if ($count > 0) {
            echo '    ';
        }
        echo $e . "\n";
        $count++;
    }
    //we can also print additionnal informations.
    if (count($success_detected) > 0) {
        echo "\n";
        echo str_replace(
            '%i',
            count($success_detected),
            _T("%i mails have been sent successfully.")
        );
    }
    exit(1);
} else {
    //if there were no errors, we just exit properly for cron to be quiet.
    exit(0);
}
