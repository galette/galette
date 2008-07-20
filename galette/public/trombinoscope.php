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
 * $Id$
 */

$base_path = '../';
require_once('../includes/galette.inc.php');

// if no custom header, use the default one.
$ch = file_exists(dirname( __FILE__)."/custom_header.php");

if ( ! $ch ) 
  include("header.php");
else
  include("custom_header.php");



// Select all adh who have put a photo & would like to appear in public views.
// FIXME: les adhérents "à jour" de cotisation => vérifier la requête

$query = "SELECT a.id_adh,a.nom_adh,a.prenom_adh,a.pseudo_adh,a.url_adh,a.date_echeance,a.bool_exempt_adh,p.format 
          FROM ".PREFIX_DB."adherents a 
          JOIN ".PREFIX_DB."pictures p 
          ON a.id_adh=p.id_adh 
          WHERE a.bool_display_info='1'
          AND (a.date_echeance > ".$DB->DBDate(time())." OR a.bool_exempt_adh='1')";

$adh =&$DB->Execute($query);
$i = 0; // used to add new rows in the table

// Here come some HTML
print '<h1>Trombinoscope</h1>'."\n";
print '<br /><br /><br />'."\n";
print '<table align="center">'."\n";
print "<tr>\n";

// main loop
while ( !$adh->EOF ) {
  $pic =& new picture($adh->fields['id_adh']);

	if ( $pic->hasPicture() ) {
	   print '<td align="center">';
	   print '<img src="../photos/'.$adh->fields['id_adh'].'.'.$pic->FORMAT.'" height="'.$pic->OPTIMAL_HEIGHT.'" width="'.$pic->OPTIMAL_WIDTH.'"';
		if ($adh->fields['pseudo_adh']) {
		    print ' alt="'.$adh->fields['pseudo_adh'].'"';
		}
	   print " /><br />";
		if ($adh->fields['url_adh']) {
		    print '<a href="'.$adh->fields['url_adh'].'">'.$adh->fields['nom_adh'].' '.$adh->fields['prenom_adh'].'</a>';
		}else{
		    print $adh->fields['nom_adh'].' '.$adh->fields['prenom_adh'];
		}
	   print "</td>\n";
	
	   $i++;
	   if ( $i%5 == 0 )
	      print "</tr>\n<tr>\n";
	}
  
  $adh->MoveNext();
}

if ( $i%5 ) // if the row isn't closed, do it before closing the table.
  print "</tr>\n";

print "</table>\n</body>\n</html>";
$adh->Close();
?>

