<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Adhesion form PDF
 *
 * User have to select members in the member's list to generate labels.
 * Format is defined in the preferences screen
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @author    Guillaume Rousse <guillomovitch@gmail.com>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

use Galette\IO\PdfAdhesionForm;
use Galette\IO\Pdf;
use Galette\Entity\Adherent;

/** @ignore */
require_once 'includes/galette.inc.php';

if (!$login->isLogged() or $login->isAdmin()
    && (!isset($_GET[Adherent::PK]) || trim($_GET[Adherent::PK]) == '')
) {
    //If not logged, or if admin without a member id ; print en empty card
    $adh = new Adherent();
} elseif ($login->isAdmin() && isset($_GET[Adherent::PK])) {
    //If admin with a member id
    $adh = new Adherent((int)$_GET[Adherent::PK]);
} elseif ($login->isLogged()) {
    //If user logged in
    $adh = new Adherent((int)$login->id);
}

$pdf = new PdfAdhesionForm($adh, $zdb, $preferences);
$pdf->download();
