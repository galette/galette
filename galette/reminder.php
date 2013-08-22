<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette reminders
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
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
require_once 'includes/galette.inc.php';

if ( $login->isCron() ) {
    //
} elseif ( !$login->isLogged() ) {
    header('location: index.php');
    die();
} elseif ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
}

$texts = new Texts($texts_fields, $preferences);
if ( isset($_POST['reminders']) || $login->isCron() ) {
    $selected = null;
    if ( isset($_POST['reminders']) ) {
        $selected = $_POST['reminders'];
    }
    $reminders = new Reminders($selected);

    $labels = false;
    $labels_members = array();
    if ( isset($_POST['reminder_wo_mail']) ) {
        $labels = true;
    }

    $list_reminders = $reminders->getList($zdb, $labels);
    if ( count($list_reminders) == 0 && !$login->isCron() ) {
        $warning_detected[] = _T("No reminder to send for now.");
    } else {
        foreach ( $list_reminders as $reminder ) {
            if ( $labels === false ) {
                //send reminders by mail
                $sent = $reminder->send($texts, $hist, $zdb);

                if ( $sent === true ) {
                    $success_detected[] = $reminder->getMessage();
                } else {
                    $error_detected[] = $reminder->getMessage();
                }
            } else {
                //generate labels for members without mail address
                $labels_members[] = $reminder->member_id;
            }
        }

        if ( $labels === true ) {
            if ( count($labels_members) > 0 ) {
                $labels_filters = new MembersList();
                $labels_filters->selected = $labels_members;
                $session['filters']['reminders_labels'] = serialize($labels_filters);
                header('location: etiquettes_adherents.php');
                die();
            } else {
                $error_detected[] = _T("There are no member to proceed.");
            }
        }

        if ( count($error_detected) > 0 ) {
            array_unshift(
                $error_detected,
                _T("Reminder has not been sent:")
            );
        }

        if ( count($success_detected) > 0 ) {
            array_unshift(
                $success_detected,
                _T("Sent reminders:")
            );
        }
    }
}

if ( !$login->isCron() ) {
    $previews = array(
        'impending' => $texts->getTexts('impendingduedate', $preferences->pref_lang),
        'late'      => $texts->getTexts('lateduedate', $preferences->pref_lang)
    );

    $tpl->assign('page_title', _T("Reminders"));
    $tpl->assign('previews', $previews);
    $tpl->assign('require_dialog', true);

    $members = new Members();
    $reminders = $members->getRemindersCount();

    $tpl->assign('count_impending', $reminders['impending']);
    $tpl->assign('count_impending_nomail', $reminders['nomail']['impending']);
    $tpl->assign('count_late', $reminders['late']);
    $tpl->assign('count_late_nomail', $reminders['nomail']['late']);

    $tpl->assign('error_detected', $error_detected);
    $tpl->assign('warning_detected', $warning_detected);
    $tpl->assign('success_detected', $success_detected);

    $content = $tpl->fetch('reminder.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
} else {
    //called from a cron. warning and errors has been stored into history
    //and probably logged
    if ( count($error_detected) > 0 ) {
        //if there are errors, we print them
        echo "\n";
        $count = 0;
        foreach ( $error_detected as $e ) {
            if ( $count > 0 ) {
                echo '    ';
            }
            echo $e . "\n";
            $count++;
        }
        //we can also print additionnal informations.
        if ( count($success_detected) > 0 ) {
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
}
