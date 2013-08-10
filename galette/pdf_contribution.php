<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Invoice/Receipt PDF for contributions
 *
 * User have to select members in the member's list to generate labels.
 * Format is defined in the preferences screen
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
 * @category  Print
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

use Galette\IO\PdfContribution;
use Galette\IO\Pdf;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfInvoice;
use Galette\Entity\PdfReceipt;
use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header("location: voir_adherent.php");
    die();
}

if ( !isset($_GET['id_cotis']) ) {
    throw new \RuntimeException('Contribution id is mandatory.');
}

$contribution = new Contribution((int)$_GET['id_cotis']);
$pdf = new PdfContribution($contribution, $zdb, $preferences);
$pdf->download();
