<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF attendance sheet generation
 *
 * User have to select members in the member's list to generate labels.
 * Format is defined in the preferences screen
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
 * @category  Print
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

use Galette\IO\Pdf;
use Galette\Repository\Members;
use Galette\Filters\MembersList;
use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() && !$login->isGroupManager() ) {
    header("location: voir_adherent.php");
    die();
}

if ( isset($session['filters']['members']) ) {
    $filters = unserialize($session['filters']['members']);
} else {
    $filters = new MembersList();
}

if ( count($filters->selected) == 0 ) {
    Analog::log('No member selected to generate attendance sheet', Analog::INFO);
    header('location:gestion_adherents.php');
    die();
}

$m = new Members();
$members = $m->getArrayList(
    $filters->selected,
    array('nom_adh', 'prenom_adh'),
    true
);

if ( !is_array($members) || count($members) < 1 ) {
    die();
}

//with or without images?
$_wimages = false;
if ( isset($_POST['sheet_photos']) && $_POST['sheet_photos'] === '1') {
    $_wimages = true;
}

define('SHEET_FONT', Pdf::FONT_SIZE-2);

/**
 * PDF attendence sheet list
 *
 * @name      SheetPdf
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class SheetPdf extends Pdf
{

    public $doc_title = null;
    public $sheet_title = null;
    public $sheet_sub_title = null;
    public $sheet_date = null;

    /**
     * Page header
     *
     * @return void
     */
    public function Header()
    {
        $this->SetFont(Pdf::FONT, '', SHEET_FONT - 2);
        $head_title = $this->doc_title;
        if ( $this->sheet_title !== null ) {
            $head_title .= ' - ' . $this->sheet_title;
        }
        if ( $this->sheet_sub_title !== null ) {
            $head_title .= ' - ' . $this->sheet_sub_title;
        }
        if ( $this->sheet_date !== null ) {
            $head_title .= ' - ' . $this->sheet_date->format(_T("Y-m-d"));
        }
        $this->Cell(0, 10, $head_title, 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
}

$doc_title = _T("Attendance sheet");
if ( isset($_POST['sheet_type']) && trim($_POST['sheet_type']) != '' ) {
    $doc_title = $_POST['sheet_type'];
}

$pdf=new SheetPdf();
$pdf->doc_title = $doc_title;
if ( isset($_POST['sheet_title']) && trim($_POST['sheet_title']) != '' ) {
    $pdf->sheet_title = $_POST['sheet_title'];
}
if ( isset($_POST['sheet_sub_title']) && trim($_POST['sheet_sub_title']) != '' ) {
    $pdf->sheet_sub_title = $_POST['sheet_sub_title'];
}
if ( isset($_POST['sheet_date']) && trim($_POST['sheet_date']) != '' ) {
    $dformat = _T("Y-m-d");
    $date = DateTime::createFromFormat(
        $dformat,
        $_POST['sheet_date']
    );
    $pdf->sheet_date = $date;
}


// Set document information
$pdf->SetTitle($doc_title);

$pdf->showPagination();
$pdf->setMargins(10, 20);
$pdf->setHeaderMargin(10);

$pdf->SetAutoPageBreak(true, 20);
$pdf->Open();

$pdf->SetFont(Pdf::FONT, '', SHEET_FONT);
$pdf->SetTextColor(0, 0, 0);

$pdf->AddPage();
$pdf->PageHeader($doc_title);

if ( $pdf->sheet_title !== null ) {
    $pdf->Cell(190, 7, $_POST['sheet_title'], 0, 1, 'C');
}
if ( $pdf->sheet_sub_title ) {
    $pdf->Cell(190, 7, $_POST['sheet_sub_title'], 0, 1, 'C');
}
if ( $pdf->sheet_date ) {
    $date_fmt = null;
    if ( PHP_OS === 'Linux' ) {
        $format = _T("%A, %B %#d%O %Y");
        $format = str_replace(
            '%O',
            date('S', $pdf->sheet_date->getTimestamp()),
            $format
        );
        $date_fmt = strftime($format, $pdf->sheet_date->getTimestamp());
    } else {
        $format = _T("Y-m-d");
        $date_fmt = $pdf->sheet_date->format($format);
    }
    $pdf->Cell(190, 7, $date_fmt, 0, 1, 'C');
}

// Header
$pdf->SetFont('', 'B');
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(110, 7, _T("Name"), 1, 0, 'C', 1);
$pdf->Cell(80, 7, _T("Signature"), 1, 1, 'C', 1);

// Data
$pdf->SetFont('');
$mcount = 0;
foreach ( $members as $m ) {
    $mcount++;
    $pdf->Cell(10, 16, $mcount, 'LTB', 0, 'R');

    if ( $m->hasPicture() && $_wimages ) {
        $p = $m->picture->getPath();

        // Set logo size to max width 30 mm or max height 25 mm
        $ratio = $m->picture->getWidth()/$m->picture->getHeight();
        if ( $ratio < 1 ) {
            if ( $m->picture->getHeight() > 14 ) {
                $hlogo = 14;
            } else {
                $hlogo = $m->picture->getHeight();
            }
            $wlogo = round($hlogo*$ratio);
        } else {
            if ( $m->picture->getWidth() > 14 ) {
                $wlogo = 14;
            } else {
                $wlogo = $m->picture->getWidth();
            }
            $hlogo = round($wlogo/$ratio);
        }

        $y = $pdf->getY() + 1;
        $x = $pdf->getX() + 1;
        $pdf->Cell($wlogo+2, 16, '', 'LTB', 0);
        $pdf->Image($p, $x, $y, $wlogo, $hlogo);
    } else {
        $x = $pdf->getX() + 1;
        $pdf->Cell(1, 16, '', 'LTB', 0);
    }

    $xs = $pdf->getX() - $x + 1;
    $pdf->Cell(100 - $xs, 16, $m->sname, 'RTB', 0, 'L');
    $pdf->Cell(80, 16, '', 1, 1, 'L');
}
$pdf->Cell(190, 0, '', 'T');

$pdf->Output(_T("attendance_sheet") . '.pdf', 'D');
