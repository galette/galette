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

/* This example illustrates the charting subclass
 * These features are still experimental
 */

require('../phppdflib.class.php');

// Starts a new pdffile object
$pdf = new pdffile;
$pdf->set_default('margin', 0);

$firstpage = $pdf->new_page("letter");

$pdf->enable('chart');
$pdf->chart->setcolor('background', 1, 0.5, 0.33);

for ($series = 1; $series < 4; $series++) {
    unset($points);
    switch ($series) {
    case 1 : $color = 'black'; break;
    case 2 : $color = 'blue'; break;
    case 3 : $color = 'green'; break;
    }
    for ($i = 0; $i < 5; $i++){
        $points[$i] = rand(-10, 10);
    }
    $pdf->chart->add_series($series, $points, $color);
}
$pdf->chart->place_chart($firstpage, 50, 50, 500, 500);

/* These headers do a good job of convincing most
 * browsers that they should launch their pdf viewer
 * program
 */
//header("Content-Disposition: inline; filename=charts.pdf");
header("Content-Type: application/pdf");
//header('Cache-Control: private');
$temp = $pdf->generate(0);
header('Content-Length: ' . strlen($temp));

/* You can now do whatever you want with the PDF file,
 * which is returned from a call to ->generate()
 * This example simply sends it to the browser, but
 * there's nothing to stop you from saving it to disk,
 * emailing it somewhere or doing whatever else you want
 * with it.
 */
echo $temp;
?>
