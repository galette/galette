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

/* The intent of this file is to show off just what
 * can be done with phppdflib
 */

require('../phppdflib.class.php');
$pdf = new pdffile;

$pdf->set_default('margin', 0);

$data = "";
for ($i = 100; $i != 255; $i++) {
    $data .= chr($i) . "\xff" . chr($i);
}
$image = $pdf->image_raw_embed($data, "/DeviceRGB", 8, 1, 154);
$firstpage = $pdf->new_page("letter");
$p['scale']["x"] = 612 / 154;
$p['scale']["y"] = 792;
$pdf->image_place($image, 0, 0, $firstpage, $p);
$p['scale']["x"] = 300/154;
$p['scale']["y"] = 150;
$p['rotation'] = 270;
$pdf->image_place($image, 690, 400, $firstpage, $p);
$p['fillcolor'] = $pdf->get_color('white');
$p['mode'] = 'fill';
$pdf->draw_rectangle(792, 0, 792 - 75, 612, $firstpage, $p);

$param["height"] = 32;
$param["font"] = "Times-Bold";
$param["mode"] = "fill+stroke";
$param['fillcolor']["red"] = 0;
$param['fillcolor']["blue"] = 0;
$param['fillcolor']["green"] = .5;
$param['strokecolor']["red"] = 0;
$param['strokecolor']["blue"] = 0;
$param['strokecolor']["green"] = 0;
$pdf->draw_text(70, 680, "Dynamically generated PDF files", $firstpage, $param);

$text = <<< EOT
Dynamically generated PDF files can enhance your website by making it more interesting and useful to your target audience.  Any time you want to deliver a print-ready document across the Internet, the PDF format is the correct delivery medium.  Whether it be downloadable or emailed, a document in PDF format is virtually guaranteed to display and print properly on any computer.
HTML can't promise this, and neither can proprietary formats, such as Word or Wordperfect documents.
EOT;

$pdf->draw_paragraph(660, 50, 300, 380, $text, $firstpage);

$pdf->draw_rectangle(650, 450, 600, 500, $firstpage, array('mode' => 'stroke'));

$x[0] = 53;
$x[1] = 503;
$x[2] = 303;
$y[0] = 447;
$y[1] = 567;
$y[2] = 347;
unset($p);
$p['mode'] = 'fill';
$p['fillcolor']['red'] = $p['fillcolor']['green'] = $p['fillcolor']['blue'] = .4;
$pdf->draw_line($x, $y, $firstpage, $p);
$x[0] = 50;
$x[1] = 500;
$x[2] = 300;
$y[0] = 450;
$y[1] = 570;
$y[2] = 350;
unset($p);
$p['mode'] = 'fill+stroke';
$p['fillcolor'] = $pdf->get_color('white');
$pdf->draw_line($x, $y, $firstpage, $p);

$fh = fopen("../doc/ptlogo.jpg", "r");
$data = fread($fh, filesize('../doc/ptlogo.jpg'));
fclose($fh);
$image = $pdf->jfif_embed($data);
$size = $pdf->get_image_size($image);
$pdf->image_place($image, 792 - $size['height'], 0, $firstpage);

$fh = fopen("../doc/powerby.png", "r");
$data = fread($fh, filesize('../doc/powerby.png'));
fclose($fh);
$image = $pdf->png_embed($data);
if (!$image) {
    echo $pdf->pop_error($n, $s);
    echo "<p>$s</p>";
    exit;
}
$pdf->image_place($image, 735, 380, $firstpage);

header("Content-Disposition: attachment; filename=showoff.pdf");
header("Content-Type: application/pdf");
echo $pdf->generate();
?>
