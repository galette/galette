<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Manage mailing recipients from ajax
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @category  Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-09-13
 */

use Analog\Analog as Analog;
use Galette\Repository\Members as Members;

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    Analog::log(
        'Trying to display ajax_recipients.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

$mailing = unserialize($session['mailing']);

$m = new Members();
$members = $m->getArrayList($_POST['recipients']);
$mailing->setRecipients($members);

$session['mailing'] = serialize($mailing);

//let's generate html for return
$html = '';
if ( count($mailing->recipients) > 0 ) {
    $html = '<p id="recipients_count">' .
        preg_replace(
            '/%s/',
            count($mailing->recipients),
            _T("You are about to send an e-mail to <strong>%s members</strong>")
        ) . '</p>';
    if ( count($mailing->unreachables) ) {
        $html .= '<p id="unreachables_count"><strong>' . count($mailing->unreachables) . ' ' .
            ((count($mailing->unreachables) !=1) ?
                _T("unreachable members:")
                : _T("unreachable member:")) . '</strong><br/>' .
            _T("Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail.") .
            '<br/><a id="btnlabels" class="button" href="etiquettes_adherents.php?from=mailing">' .
            _T("Generate labels") . '</a></p>';
    }
} else {
    if ( count($mailing->unreachables) ) {
        $html .= '<p id="recipients_count"><strong>' . _T("None of the selected members has an email address.") . '</strong></p>';
    } else {
        $html .= '<p id="recipients_count"><strong>' . _T("No member selected (yet).") . '</strong></p>';
    }
}
echo $html;
