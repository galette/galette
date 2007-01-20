<?php
/* liste_membres.php - Part of the Galette Project
 * 
 * Copyright (c) 2006 Loïs 'GruiicK' Taulelle
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

include("includes/config.inc.php"); 
include("includes/database.inc.php");

// if no custom header, use the default one.
$ch = file_exists(dirname( __FILE__)."/custom_header.php");

if ( ! $ch ) 
  include("header.php");
else
  include("custom_header.php");

// Here come some HTML
print '<h1>Trombinoscope</h1>';
print '<br /><br /><br />';
print '<ul>';

// Select all adh who would like to appear in public views.
// FIXME: les adhérents "à jour" de cotisation => vérifier la requête
$query = "SELECT prenom_adh, nom_adh, pseudo_adh, url_adh 
          FROM ".PREFIX_DB."adherents 
          WHERE bool_display_info='1'
          OR bool_exempt_adh = '1'
          ORDER BY nom_adh, prenom_adh";

$adh =&$DB->Execute($query);

// main loop
while ( !$adh->EOF ) {
  print "<li>".$adh->fields['prenom_adh']." ".$adh->fields['nom_adh'];
  if ($adh->fields['pseudo_adh']) {
    print " ".$adh->fields['pseudo_adh'];
  }
  if ($adh->fields['url_adh']) {
    print " - <a href=\"".$adh->fields['url_adh']."\">site web</a>";
  }

  print '</li>';

  $adh->MoveNext();
}

// closing list
print '</ul>';

// Here you can include your site's footer.
//include("footer.php");
print '</body></html>';

$adh->Close();
?>

