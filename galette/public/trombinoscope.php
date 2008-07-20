<?php

// Copyright © 2006 Alexandre 'laotseu' DE DOMMELIN
// Copyright © 2007-2008 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * Trombinoscope
 *
 * On affiche les adhérents qui ont souhaité rendre leurs informations 
 * publiques et qui ont placé une photo sur Galette
 *
 * @package    Galette
 *
 * @author     Alexandre 'laotseu' DE DOMMELIN
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2006 Alexandre 'laotseu' DE DOMMELIN
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

$base_path = '../';
require_once('../includes/galette.inc.php');

$query = "SELECT a.id_adh,a.nom_adh,a.prenom_adh,a.pseudo_adh,a.url_adh
          FROM ".PREFIX_DB."adherents a 
          JOIN ".PREFIX_DB."pictures p 
          ON a.id_adh=p.id_adh 
          WHERE a.bool_display_info='1'
          AND (a.date_echeance > ".$DB->DBDate(time())." OR a.bool_exempt_adh='1')";

/*$adh =&$DB->Execute($query);
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
$adh->Close();*/

$resultat = &$DB->Execute($query);

while (!$resultat->EOF) {
	$members[$compteur]["id_adh"] = $resultat->fields['id_adh'];
	$members[$compteur]["nom"] = htmlentities(strtoupper($resultat->fields['nom_adh']),ENT_QUOTES);
	$members[$compteur]["prenom"] = htmlentities($resultat->fields['prenom_adh'], ENT_QUOTES);
	$members[$compteur]["pseudo"] = htmlentities($resultat->fields['pseudo_adh'], ENT_QUOTES);
	$members[$compteur]["url"] = $resultat->fields['url_adh'];
	//Picutre infos
	$pic =& new picture($resultat->fields['id_adh']);
	$members[$compteur]["pic_format"] = $pic->FORMAT;
	$members[$compteur]["pic_height"] = $pic->OPTIMAL_HEIGHT;
	$members[$compteur]["pic_width"] = $pic->OPTIMAL_WIDTH;

	$resultat->MoveNext();
}
$resultat->Close();

$tpl->assign('page_title', _T("Trombinoscope"));
$tpl->assign("members",$members);
$content = $tpl->fetch("trombinoscope.tpl");
$tpl->assign("content",$content);
$tpl->display("public_page.tpl");

?>

