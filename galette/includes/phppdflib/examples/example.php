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

/* This is a demo file to demonstrate the usage
 * of phppdflib
 */

/* Simply copy the phppdflib.class.php file to an
 * accessible location on your web server, and include()
 * it in any scripts that you want to generate pdf
 * files from
 */
require('../phppdflib.class.php');

// Starts a new pdffile object
$pdf = new pdffile;

/* Use the defaults system to turn off page
 * margins
 */
$pdf->set_default('margin', 0);

/* You can create as many pages as you want.
 * The parameter is the size of the page:
 * The following keywords are recognized:
 * "letter", "legal", "executive", "tabloid",
 * "a3", "a4", "a5"
 * or the actual size in the format:
 * "[width]x[height][in|cm]"
 * See examples at the end of this file.
 */
$firstpage = $pdf->new_page("letter");

/* Using the ->draw_text() method:
 * We're going to set up the text parameters,
 * first the height
 */
$param["height"] = 12;
/* Now we set the color to red
 */
$param["fillcolor"] = $pdf->get_color('#ff3333');
/* Set the font.  Possible values are:
   Courier
   Courier-Bold
   Courier-Oblique
   Courier-BoldOblique
   Helvetica
   Helvetica-Bold
   Helvetica-Oblique
   Helvetica-BoldOblique
   Times-Roman
   Times-Bold
   Times-Italic
   Times-BoldItalic
   Symbol
   ZapfDingbats
 * These names must be exact, and are case-
 * sensitive.  Default is Helvetica
 */
$param["font"] = "Times-Italic";
// Rotate the text 60 degrees
$param["rotation"] = 60;
/* Now we'll place our text string on the page */
$pdf->draw_text(10, 200, "This is red, italic text", $firstpage, $param);
// Alter some parameters and place more text
$param["font"] = "Courier-Bold";
$param["mode"] = "fill+stroke";
$param["height"] = 32;
$param["rotation"] = 0;
$pdf->draw_text(10, 100, "This is red, Courier-Bold text", $firstpage, $param);
/* By omitting the final parameter, we get the same
 * text placed with default text settings
 */
$pdf->draw_text(10, 150, "This is not red text", $firstpage);

/* simply draws a rectange on the page.
 * Specify the top,left,bottom,right locations
 * (in that order) and the ID of the page to place
 * it on
 */
$pdf->draw_rectangle( 30, 10, 10, 30, $firstpage);
/* Lets draw a rectangle with some fancy parameters
 * First we'll set the color
 */
$param["red"] = 0;
$param["green"] = 1;
$param["blue"] = 0;
/* Now we'll set the line width, this is in pdf units
 * (1/72 of an inch)
 */
$param["width"] = 5;
/* Place a rectangle with these parameters */
$pdf->draw_rectangle( 250, 200, 200, 250, $firstpage, $param);

/* Add a preexisting image to the page
 *
 * Manually creating a bitmap image like this isn't terribly
 * difficult, but somewhat tedious if there's any complexity
 * to the image at all.
 *
 * $data is being loaded up with a small, simple RGB image
 * that we'll use to demonstrate embedding and placement
 */
$data = "\xff\x00\x00\x00\xff\x00\x00\x00\xff";

/* ->image_raw_embed() it not intended to be the end-all
 * user interface, but this is how you use it to embed
 * something manually:
 * The first parameter is the image data itself
 * The second is the colorspace.
 * The third is the number of bits per pixel
 * The fourth is the height of the image
 * The fifth is the width of the image
 * The sixth parameter is the encoding, which has possibilities
 * such as /DCTEncode (the .jpeg compression method) If left
 * off, no encoding is used.
 * This method returns an ID code for the embedded image,
 * which is used to place the image
 */
$image = $pdf->image_raw_embed($data, "/DeviceRGB", 8, 1, 3 );

/* This example shows how to use the ->jfif_embed()
 * funtion, which can be used to embed JFIF images
 * (commonly know as JPEGs)
 * ->jfif_embed() needs only the data itself, as it
 * is capable of extracting other required data (such
 * as height and width) from the data.
 * This example is commented out because we don't ship
 * an example JPEG image with the library.  Just change
 * $fn to the [path]filename of a jpeg image and use
 * the method to embed it, you can then use
 * ->image_place() to place it on a page.
 * Obviously, you can get the JFIF(JPEG) data from anywhere,
 * such as a database query, or an HTTP POST operation.
 * The library doesn't care, just as long as it's valid
 * JFIF formatted data.
 * If you try to embed an image and your PDF viewer complains
 * of corruption, try changing the parameters under which
 * the original image was created.  Adobe's PDF viewer
 * (for example) does not understand all JFIF images.
 * Saving from Gimp with "Optimize" turned on (for
 * example) will create a JPEG that Adobe Acrobat
 * can't display.
$fn = "example.jpg";
$fh = fopen($fn, "r");
$data = fread($fh, filesize($fn));
fclose($fh);
$image = $pdf->jfif_embed($data);
*/

/* Once the image is embedded in the PDF, it can be
 * placed as many or few times as you like.  This is a
 * very nice feature of PDFs, as it allows you to place
 * the same image at (for example) different scalings, thus
 * saving space in the file.
 * The first parameter is an ID for an image
 * The second is the bottom edge of the image (in PDF units)
 * The third is the left edge (in PDF units)
 * The fourth is the page ID to place the image on
 * The fifth is a parameters array that can specify rotation
 * and scaling
 * Here are several example of image placement
 */
$pdf->image_place($image, 200, 300, $firstpage);
$pdf->image_place($image, 300, 300, $firstpage, array('scale' => 10, 'rotation' => 30));
$pdf->image_place($image, 400, 300, $firstpage, array('scale' => 25, 'rotation' => 60));

/* A quick example for creating additional pages
 * and placing objects on them.
 */
$secondpage = $pdf->new_page("legal");
$pdf->draw_rectangle( 998, 10, 10, 602, $secondpage);
$pdf->draw_text(300, 450, "Page #2", $secondpage);
$pdf->draw_text(300, 400, "backslashes (\) cause no problems", $secondpage);

/* Circle command is new to 2.1
 */
$pdf->draw_circle(150, 200, 50, $secondpage, array('mode' => 'stroke',
												   'strokecolor' => $pdf->get_color('blue'),
                                                   'width' => 5));
$pdf->draw_circle(300, 200, 35, $secondpage, array('mode' => 'fill'));
$pdf->draw_circle(450, 200, 50, $secondpage, array('mode' => 'fill+stroke',
                                                   'fillcolor' => $pdf->get_color('red')));

/* Uses the absolute page size notation to create
 * a notecard sized page
 */
$thirdpage = $pdf->new_page("5x3in");
$pdf->draw_rectangle( 198, 18, 18, 342, $thirdpage);
$pdf->draw_text(150, 100, "Page #3", $thirdpage);

/* Uses the absolute page size notation to create
 * a 50x30 centimeter page
 */
$fourthpage = $pdf->new_page("50x30cm");
$pdf->draw_text(150, 100 ,"Page #4" ,$fourthpage);

/* These headers do a good job of convincing most
 * browsers that they should launch their pdf viewer
 * program
 */
header("Content-Disposition: filename=example.pdf");
header("Content-Type: application/pdf");
$temp = $pdf->generate();
header('Content-Length: ' . strlen($temp));

/* You can now do whatever you want with the PDF file,
 * which is returned from a call to ->generate()
 * This example simply sends it to the browser, but
 * there's nothing to stop you from saving it to disk,
 * emailing it somewhere or doing whatever else you want
 * with it (such as email it somewhere or store it in
 * a database field)
 */
echo $temp;

?>