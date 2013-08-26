<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF groups and members list
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
 * @since     Available since 0.7.4dev - 2013-02-02
 */

use Galette\IO\Pdf;
use Galette\Repository\Groups;
use Analog\Analog;

/** @ignore */
require_once 'includes/galette.inc.php';


if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() && !$login->isGroupManager() ) {
    header('location: voir_adherent.php');
    die();
}

if ( isset($_GET['gid']) ) {
    //work on a specific group
}

define('SHEET_FONT', Pdf::FONT_SIZE-2);

/**
 * PDF groups and members list
 *
 * @name      GroupsPdf
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class GroupsPdf extends Pdf
{

    /**
     * Page header
     *
     * @return void
     */
    public function Header()
    {
        $this->Cell(0, 10, _T("Members by groups"), 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
}

$doc_title = _T("Members by groups");

$pdf=new GroupsPdf();

// Set document information
$pdf->SetTitle($doc_title);

$pdf->showPagination();
$pdf->setMargins(10, 20);
$pdf->setHeaderMargin(10);

$pdf->SetAutoPageBreak(true, 20);
$pdf->Open();

$pdf->AddPage();
$pdf->PageHeader($doc_title);

$pdf->SetFont(Pdf::FONT, '', SHEET_FONT);
$pdf->SetTextColor(0, 0, 0);


$groups = new Groups();
$groups_list = $groups->getList();
//var_dump($groups_list);

$first = true;
foreach ( $groups_list as $group ) {
    $id = $group->getId();
    if ( !$login->isGroupManager($id) ) {
        Analog::log(
            'Trying to display group ' . $id . ' without appropriate permissions',
            Analog::INFO
        );
        continue;
    }
    // Header
    if ( $first === false ) {
        $pdf->ln(5);
    }
    $pdf->SetFont('', 'B', SHEET_FONT + 1);
    $pdf->Cell(190, 4, $group->getName(), 0, 1, 'C');
    $pdf->SetFont('', '', SHEET_FONT);

    $managers_list = $group->getManagers();
    $managers = array();
    foreach ( $managers_list as $m ) {
        $managers[] = $m->sfullname;
    }
    if ( count($managers) > 0 ) {
        $pdf->Cell(190, 4, _T("Managers:") . ' ' . implode(', ', $managers), 0, 1, 'R');
    }
    $pdf->ln(3);

    $pdf->SetFont('', 'B');
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(80, 7, _T("Name"), 1, 0, 'C', 1);
    $pdf->Cell(50, 7, _T("Email"), 1, 0, 'C', 1);
    $pdf->Cell(30, 7, _T("Phone"), 1, 0, 'C', 1);
    $pdf->Cell(30, 7, _T("GSM"), 1, 1, 'C', 1);

    $pdf->SetFont('', 'B');

    $members = $group->getMembers();

    foreach ( $members as $m ) {
        $pdf->Cell(80, 7, $m->sname, 1, 0, 'L');
        $pdf->Cell(50, 7, $m->email, 1, 0, 'L');
        $pdf->Cell(30, 7, $m->phone, 1, 0, 'L');
        $pdf->Cell(30, 7, $m->gsm, 1, 1, 'L');
    }
    $pdf->Cell(190, 0, '', 'T');
    $first = false;
}


$pdf->Output(_T("groups_list") . '.pdf', 'D');
