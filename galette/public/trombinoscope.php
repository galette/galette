<?php
/* trombinoscope.php - Part of the Galette Project
 * 
 * Copyright (c) 2006 Alexandre 'laotseu' DE DOMMELIN
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

include("includes/config.inc.php"); 
include("includes/database.inc.php");
include("includes/picture.class.php");


// if no custom header, use the default one.
$ch = file_exists(dirname( __FILE__)."/custom_header.php");

if ( ! $ch ) 
  include("header.php");
else
  include("custom_header.php");



// Select all adh who have put a photo & would like to appear in public views.
$query = "SELECT a.id_adh,a.nom_adh,a.prenom_adh,a.pseudo_adh,p.format FROM adherents a JOIN pictures p ON a.id_adh=p.id_adh WHERE a.bool_display_info='1'";

$adh =&$DB->Execute($query);
$i = 0; // used to add new rows in the table

print '<table align="center">';
print '<tr>';

while ( !$adh->EOF ) {
  $pic =& new picture($adh->fields['id_adh']);

  if ( $pic->hasPicture() ) {
    print '<td align="center">';
    print '<img src="../photos/'.$adh->fields['id_adh'].'.'.$pic->FORMAT.'" height="'.$pic->OPTIMAL_HEIGHT.'" width="'.$pic->OPTIMAL_WIDTH.'" />';
    print '<br />';
    print $adh->fields['nom_adh']." ".$adh->fields['prenom_adh'];
    print '</td>';

    $i++;
    if ( $i%5 == 0 )
      print '</tr><tr>';
  }
  
  $adh->MoveNext();
}

if ( $i%5 ) // if the row isn't be closed, do it before closing the table.
  print '</tr>';

print '</table>';
print '</body></html>';
$adh->Close();
?>

