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

require('../phppdflib.class.php');

$pdf = new pdffile;

$pdf->enable('packer');
$page = $pdf->packer->new_page();

$pdf->set_default('smode', 'fill');

$pdf->set_default('fillcolor', $pdf->get_color('red'));
$space = new field(100, 200, 100, 200);
$pdf->packer->allocate($space);
$pdf->draw_rectangle(200, 100, 100, 200, $page);

$pdf->set_default('fillcolor', $pdf->get_color('blue'));
$space = new field(150, 300, 150, 400);
$pdf->packer->allocate($space);
$pdf->draw_rectangle(400, 150, 150, 300, $page);

$pdf->set_default('fillcolor', $pdf->get_color('green'));
$space = new field(400, 600, 600, 800);
$pdf->packer->allocate($space);
$pdf->draw_rectangle(800, 400, 600, 600, $page);

$pdf->set_default('fillcolor', $pdf->get_color('black'));
$pdf->set_default('height', 10);
$text = implode('', file('text.txt'));
$pdf->packer->fill_text($text);

$pdf->set_default('smode', 'stroke');
$pdf->set_default('strokecolor', $pdf->get_color('#cccccc'));

foreach ($pdf->packer->fields as $f) {
    $pdf->draw_rectangle($f->t, $f->l, $f->b, $f->r, $page);
    $x[0] = $f->l;
    $x[1] = $f->r;
    $y[0] = $f->b;
    $y[1] = $f->t;
    $pdf->draw_line($x, $y, $page);
}

$page = $pdf->new_page();
$pdf->set_default('height', 9);
$pdf->draw_paragraph(720, 72, 72, 540,
                     $pdf->_print_r($pdf->packer->fields),
                     $page);

/* These headers do a good job of convincing most
 * browsers that they should launch their pdf viewer
 * program
 */
header("Content-Disposition: filename=example.pdf");
header("Content-Type: application/pdf");
$temp = $pdf->generate(0);
header('Content-Length: ' . strlen($temp));
echo $temp;

?>