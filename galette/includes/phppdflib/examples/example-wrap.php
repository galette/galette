<?php
/*
   php pdf generation library
   Copyright (C) Potential Technologies 2002
   http://www.potentialtech.com

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

   $Id$
*/
set_time_limit(600);
require("../phppdflib.class.php");

// Starts a new pdffile object
$pdf = new pdffile;

$pdf->set_default('margin', 0);
$pdf->set_default('font', 'Courier');
$pdf->set_default('height', 10);

$fname = "../phppdflib.class.php";
$fh = fopen($fname, "r");
$data = fread($fh, filesize($fname));
fclose($fh);

$start = gettimeofday();

$p = explode("\n", $data);
$top =720;
$page = $firstpage = $pdf->new_page("letter");
foreach ($p as $one) {
    while (is_string($one)) {
        $one = $pdf->draw_one_paragraph($top, 72, 72, 540, $one, $page);
        if (is_string($one)) {
            $page = $pdf->new_page("letter");
            $top = 720;
        } else {
            $top = $one;
        }
    }
}

$end = gettimeofday();

$elapsed = $end['sec'] - $start['sec'] +
           (($end['usec'] - $start['usec']) / 1000000);

$pdf->draw_text(72, 730, "Time taken : $elapsed", $firstpage);

header("Content-Disposition: attachment; filename=example-wrap.pdf");
header("Content-Type: application/pdf");

echo $pdf->generate(9);
?>
