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

/* This example illustrates the template subclass
 * These features are still experimental
 */

require('../phppdflib.class.php');

// Starts a new pdffile object
$pdf = new pdffile;

$page = $pdf->new_page("letter");

$pdf->enable('template');

$t1 = $pdf->template->create();
/* Set the overall size of this template.
 * This will be important once auto-placement
 * is working
 */
$pdf->template->size($t1, 500, 80);
/* Put a rectangle at the lower left of the template
 */
$pdf->template->rectangle($t1, 0, 0, 20, 20);
// These next two will look like a lolipop
// A circle on the template
$pdf->template->circle($t1, 200, 5, 12, array('mode' => 'fill'));
// Add a line
$pdf->template->line($t1,
					 array(0 => 200, 1 => 250),
                     array(0 => 5, 1 => 25));
/* Put a fixed text string to the right of the rectangle
 */
$pdf->template->text($t1, 25, 0, 'This never changes');
/* Put a variable text "field" named "var" above the rectangle
 */
$pdf->template->field($t1, 0, 25, 'var');
// Fixed paragraph string
$pdf->template->paragraph($t1, 0, 340, 70, 400, 'This is text that will wrap so it fits the space');
// Variable paragraph
$pdf->template->pfield($t1, 0, 410, 70, 470, 'para');

/* To demonstrate the graphic capabilities of templates
 * we're going to do some interesting stuff ...
 * First we'll make three images, each 1 pixel square
 * of a solid color (1 red, 1 blue, 1 green) and embed
 * them in the pdf file
 * If you're wondering, this is a silly way to do this.
 * It would be easier and smarter to use filled rectangles,
 * but this is not intended as a "best practice" example,
 * but only to illustrate capibilities.
 */
$d = "\xff\x00\x00";
$im[0] = $pdf->image_raw_embed($d, '/DeviceRGB', 8, 1, 1);
$d = "\x00\xff\x00";
$im[1] = $pdf->image_raw_embed($d, '/DeviceRGB', 8, 1, 1);
$d = "\x00\x00\xff";
$im[2] = $pdf->image_raw_embed($d, '/DeviceRGB', 8, 1, 1);

// Now we'll attach a red rectangle to the template
$pdf->template->image($t1, 280, 5, 20, 20, $im[0]);

/* Now we'll place an "image field" (i.e. a "variable" image)
 * next to the previous image.  When the band is placed, an
 * image will be dynamically selected to insert into the
 * space we create here
 */
$pdf->template->ifield($t1, 310, 5, 20, 20, 'image');

/* Now got through a loop and manually place 7 of these
 * templates on this page
 */
$running = '';
for ($i = 0; $i < 8; $i++) {
	$running .= pow(4, $i) . ' ';
	$pdf->template->place($t1,
    					  $page,
                          0,
                          $i * 80,
                          array('var'   => "number $i",
                                'image' => $im[$i % 3],
                                'para'  => $running));
}

header("Content-Disposition: filename=template.pdf");
header("Content-Type: application/pdf");
echo $pdf->generate(0);

?>
