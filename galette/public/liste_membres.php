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
 */

$base_path = '../';
require_once('../includes/galette.inc.php');

// Select all adh who would like to appear in public views.
// FIXME: les adhérents "à jour" de cotisation => vérifier la requête
$query = "SELECT a.id_adh,a.nom_adh,a.prenom_adh,a.pseudo_adh,a.url_adh,a.info_public_adh,a.date_echeance,a.bool_exempt_adh,p.format 
          FROM ".PREFIX_DB."adherents a 
          JOIN ".PREFIX_DB."pictures p 
          ON a.id_adh=p.id_adh 
          WHERE a.bool_display_info='1'
          AND (a.date_echeance > ".$DB->DBDate(time())." OR a.bool_exempt_adh='1')";

$adh =&$DB->Execute($query);

print "<html>\n<body>\n<h1>Liste des membres</h1>\n";
print "<table align=\"center\">\n<tr><th>"._T("Name")."</th><th>"._T("Pseudo")."</th><th>"._T("Comments")."</th></tr>";

// main loop
while ( !$adh->EOF ) {
  if ($adh->fields['url_adh']) {
    print '<tr><td><a href="'.$adh->fields['url_adh'].'">'.$adh->fields['prenom_adh'].' '.$adh->fields['nom_adh'].'</a></td>';
  } else {
	 print '<tr><td>'.$adh->fields['prenom_adh'].' '.$adh->fields['nom_adh'].'</td>';
  }
  if ($adh->fields['pseudo_adh']) {
    print "<td>".$adh->fields['pseudo_adh']."</td>\n";
  } else {
	 print "<td>&nbsp</td>\n";
  }
  if ($adh->fields['info_public_adh']) {
    print "<td>".$adh->fields['info_public_adh']."</td></tr>\n";
  } else {
	 print "<td>&nbsp</td></tr>\n";
  }
  $adh->MoveNext();
}

// closing list

print "</table>\n</body>\n</html>";
$adh->Close();

// Here you can include your site's footer.
//include("footer.php");
print "\n</body>\n</html>";

?>

