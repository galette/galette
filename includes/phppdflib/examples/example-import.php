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

// Starts a new pdffile object
$pdf = new pdffile;

$pdf->enable('import');

// Change this to grab a pre-existing PDF file from somewhere
$d = file_get_contents('example.pdf');

if ($pdf->import->append($d)) {
    echo "No errors\n";
} else {
    echo "Error!\n";
}

?>