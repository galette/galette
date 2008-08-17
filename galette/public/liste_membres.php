<?php

// Copyright © 2006 Loïs 'GruiicK' Taulelle
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
 * Liste publique des adhérents
 *
 * On affiche les adhérents qui ont souhaité rendre leurs informations 
 * publiques.
 *
 * @package    Galette
 *
 * @author     Loïs 'GruiicK' Taulelle
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2006 Loïs 'GruiicK' Taulelle
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

$base_path = '../';
require_once('../includes/galette.inc.php');

$query = "SELECT a.id_adh,a.nom_adh,a.prenom_adh,a.pseudo_adh,a.url_adh,a.info_public_adh,a.titre_adh
          FROM ".PREFIX_DB."adherents a 
          WHERE a.bool_display_info='1'
          AND (a.date_echeance > ".$DB->DBDate(time())." OR a.bool_exempt_adh='1')";

$resultat = &$DB->Execute($query);

while (!$resultat->EOF) {
	$members[$compteur]["id_adh"] = $resultat->fields['id_adh'];
	$members[$compteur]["nom"] = strtoupper(utf8_encode($resultat->fields['nom_adh']));
	$members[$compteur]["prenom"] = utf8_encode($resultat->fields['prenom_adh']);
	$members[$compteur]["pseudo"] = utf8_encode($resultat->fields['pseudo_adh']);
	$members[$compteur]["url"] = $resultat->fields['url_adh'];
	$members[$compteur]["genre"] = $resultat->fields['titre_adh'];
	$members[$compteur]["infos"] = utf8_encode($resultat->fields['info_public_adh']);
	$resultat->MoveNext();
	$compteur++;
}
$resultat->Close();

$tpl->assign('page_title', _T("Members list"));
$tpl->assign("members",$members);
$content = $tpl->fetch("liste_membres.tpl");
$tpl->assign("content",$content);
$tpl->display("public_page.tpl");
?>

