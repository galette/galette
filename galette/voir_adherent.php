<?php // -*- Mode: PHP; tab-width: 2; indent-tabs-mode: nil; c-basic-offset: 4 -*-
/** 
 * Visualisation d'un adhérent
 *
 * Affichage des cractéristiques d'un adhérent et possibilités de :
 * - Modifier ces caractéristiques
 * - De visualiser les contributions
 * - De saisir une contribution
 * - De générer la carte de membre en pdf
 * 
 * @package    Galette
 * @author     Frédéric Jaqcuot
 * @copyright  2003 Frédéric Jaqcuot
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL License 2.0
 * @version    $Id$
 * @since      Disponible depuis la Release 0.60
 */
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//

/**
 * 
 */
 
require_once('includes/galette.inc.php');

if ($_SESSION["logged_status"]==0) {
	header("location: index.php");
	die();
}

$id_adh = get_numeric_form_value("id_adh", "");

if ($_SESSION["admin_status"]==0)
	$id_adh = $_SESSION["logged_id_adh"];

if ($id_adh=="") {
	header("location: index.php");
	die();
}
if ($_SESSION['galette']['pdf_error']) {
	$error_detected[] = $_SESSION['galette']['pdf_error_msg'];
}

include(WEB_ROOT."includes/dynamic_fields.inc.php");

// Set caller page ref for cards error reporting	
    $_SESSION['galette']['caller']='voir_adherent.php?id_adh='.$id_adh;	

	$requete = "SELECT * 
		    FROM ".PREFIX_DB."adherents 
		    WHERE id_adh=$id_adh";
	$result = &$DB->Execute($requete);
        if ($result->EOF)
		header("location: index.php");

	$adherent=array();
	while (list($key,$val)=each($result->fields))
	{
		if (!is_numeric($key))
		switch ($key)
		{
			// convert dates
			case 'date_crea_adh':
			case 'ddn_adh':
			case 'date_echeance':
				if ($val!='')
				{
					list($a,$m,$j)=split("-",$val);
					$adherent[$key]="$j/$m/$a";
				}
				break;
			case 'bool_display_info':
			case 'bool_admin_adh':
			case 'bool_exempt_adh':
				if ($val==1)
					$adherent[$key]=_T("Yes");
				else
					$adherent[$key]=_T("No");
				break;
			default:
				$adherent[$key]=htmlentities(stripslashes(addslashes($val)), ENT_QUOTES);
				break;
		}
	}

        switch($adherent['titre_adh'])
        {
                case "1" :
                        $adherent['titre_adh'] = _T("Mr.");
                        break;
                case "2" :
                        $adherent['titre_adh'] = _T("Mrs.");
                        break;
		case "3":
			$adherent['titre_adh'] = _T("Miss.");
			break;
                default :
                        $adherent['titre_adh'] = '';
        }

	if ($adherent['activite_adh']==1)
		$adherent['activite_adh']=_T("Active");
	else
		$adherent['activite_adh']=_T("Inactive");

	$adherent['info_adh'] = nl2br($adherent['info_adh']);
	$adherent['info_public_adh'] = nl2br($adherent['info_public_adh']);
	
        $requete = "SELECT libelle_statut
		    FROM ".PREFIX_DB."statuts
		    WHERE id_statut=".$adherent['id_statut']."
		    ORDER BY priorite_statut";
        $result = &$DB->Execute($requete);
        if (!$result->EOF)
                $adherent['libelle_statut'] = _T($result->fields['libelle_statut']);
        $result->Close();

	// declare dynamic field values
	$adherent['dyn'] = get_dynamic_fields($DB, 'adh', $adherent["id_adh"], true);

	// - declare dynamic fields for display
	$disabled['dyn'] = array();
	$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'adh', $_SESSION["admin_status"], $adherent['dyn'], $disabled['dyn'], 0);

	$adherent['pref_lang_img'] = utf8_decode($i18n->getFlagFromId($adherent['pref_lang']));
	$adherent['pref_lang'] = ucfirst(utf8_decode($i18n->getNameFromId($adherent['pref_lang'])));
	/** FIXME: PREF_CARD_SELF is not defined */
	$adherent['pref_card_self'] = PREF_CARD_SELF;

	// picture size
	$picture = new picture($id_adh);
	$adherent['picture_height'] = $picture->getOptimalHeight();
	$adherent['picture_width'] = $picture->getOptimalWidth();
	
	if(isset($error_detected))
		$tpl->assign("error_detected",$error_detected);
	$tpl->assign("data",$adherent);
	$tpl->assign("dynamic_fields",$dynamic_fields);
	$tpl->assign("time",time());
	$content = $tpl->fetch("voir_adherent.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
