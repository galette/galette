<? // -*- Mode: PHP; tab-width: 2; indent-tabs-mode: nil; c-basic-offset: 4 -*-
/* voir_adherent.php
 * - Visualisation d'une fiche adhérent
 * Copyright (c) 2004 Frédéric Jaqcuot
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
	include(WEB_ROOT."includes/database.inc.php");
        include(WEB_ROOT."includes/session.inc.php");
	include(WEB_ROOT."includes/functions.inc.php");
        include_once("includes/i18n.inc.php"); 
	include(WEB_ROOT."includes/smarty.inc.php");
	include(WEB_ROOT."includes/dynamic_fields.inc.php"); 
	
	$id_adh = "";
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		$id_adh = $_SESSION["logged_id_adh"];

	if (isset($_GET["id_adh"]))
		if (is_numeric($_GET["id_adh"]))
			$id_adh = $_GET["id_adh"];

	if ($_SESSION["admin_status"]==0)
		$id_adh = $_SESSION["logged_id_adh"];
	if ($id_adh=="")
		header("location: index.php");

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
			case 'date_echeance':
			case 'ddn_adh':
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
	$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'adh', $_SESSION["admin_status"], $adherent['dyn'], $disabled['dyn'], 1);

        $tpl->assign("data",$adherent);
	$tpl->assign("dynamic_fields",$dynamic_fields);
	$tpl->assign("time",time());
	$content = $tpl->fetch("voir_adherent.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
/*																														
?>  
			<H1 class="titre"><? echo _T("Member Profile"); ?></H1>					
			<BLOCKQUOTE>
				<DIV align="center">
				<TABLE border="0"> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Name:"); ?></B></TD>
<?
	$nom_adh_ext = "";
	switch($titre_adh)
	{
		case "1" :
			$nom_adh_ext .= _T("Mr.");
			break;
		case "2" :
			$nom_adh_ext .= _T("Mrs.");
			break;
		default :
			$nom_adh_ext .= _T("Miss.");
	}
	$nom_adh_ext .= " ".htmlentities(strtoupper(custom_html_entity_decode($nom_adh)), ENT_QUOTES)." ".ucfirst(strtolower($prenom_adh));
?>
						<TD bgcolor="#EEEEEE"><? echo $nom_adh_ext; ?></TD>
<?
	$image_adh = "";
	if (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".jpg"))
		$image_adh = WEB_ROOT . "photos/tn_" . $id_adh . ".jpg";
	elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".gif"))
		$image_adh = WEB_ROOT . "photos/tn_" . $id_adh . ".gif";
	elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".png"))
		$image_adh = WEB_ROOT . "photos/tn_" . $id_adh . ".png";
									
    if (function_exists("ImageCreateFromString"))
    {
        if ($image_adh != "")
            $imagedata = getimagesize($image_adh);
        else
            $imagedata = getimagesize(WEB_ROOT . "photos/default.png");
    }
    else
        $imagedata = array("130","");

	if ($_SESSION["admin_status"]!=0)
		$rowspan_photo = "8";
	else
		$rowspan_photo = "5";
?>
						<TD colspan="2" rowspan="<? echo $rowspan_photo; ?>" align="center">
<?
	if ($image_adh != "")
	{
?> 
                            <A href="photo.php?&id_adh=<? echo $id_adh."&nocache=".time(); ?>"><IMG src="photo.php?tn=1&id_adh=<? echo $id_adh."&nocache=".time(); ?>" border="1" alt="<? echo _T("Picture"); ?>" width="<? echo $imagedata[0]; ?>" height="<? echo $imagedata[1]; ?>"></A>
<?
    }
    else
    {
?>
                            <IMG src="photo.php?tn=1&id_adh=<? echo $id_adh."&nocache=".time(); ?>" border="1" alt="<? echo _T("Picture"); ?>" width="<? echo $imagedata[0]; ?>" height="<? echo $imagedata[1]; ?>"></A>

<?
    }
?>
                        </TD>
					</TR>
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Nickname:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $pseudo_adh; ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("birth date:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $ddn_adh; ?>&nbsp;</TD>
					</TR>
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Status:"); ?></B></TD> 
<?
	$requete = "SELECT libelle_statut
								FROM ".PREFIX_DB."statuts
								WHERE id_statut=".$id_statut."
								ORDER BY priorite_statut";
	$result = &$DB->Execute($requete);
	if (!$result->EOF)
		$libelle_statut = gettext($result->fields["libelle_statut"]);
	$result->Close();
?>
						<TD bgcolor="#EEEEEE"><? echo $libelle_statut ?>&nbsp;</TD> 
					</TR>
					<TR>
						<TD bgcolor="#DDDDFF"><B><? echo _T("Profession:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $prof_adh; ?>&nbsp;</TD> 
					</TR> 
					<TR>
						<TD bgcolor="#DDDDFF"><B><? echo _T("Be visible in the<br /> members list :"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($bool_display_info=="1") echo _T("Yes"); else echo _T("No"); ?></TD> 
					</TR>
<?
	if ($_SESSION["admin_status"]!=0)
	{
?>
					<TR>
						<TD bgcolor="#DDDDFF"><B><? echo _T("Account:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($activite_adh=="1") 	echo _T("Active"); else echo _T("Inactive"); ?></TD>
					</TR>
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Galette Admin:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($bool_admin_adh=="1") echo _T("Yes"); else echo _T("No"); ?></TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Freed of dues:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($bool_exempt_adh=="1") echo _T("Yes"); else echo _T("No"); ?></TD> 
                        <TD bgcolor="#DDDDFF"><B><? echo _T("Language:"); ?><B></TD>
                        <TD bgcolor="#EEEEEE"><IMG SRC="<? echo "lang/".$pref_lang.".gif"; ?>" align="left"> <? echo ucfirst(_T($pref_lang)); ?></TD>
					</TR> 
<?
	}
?>
                    <TR>
						<TD colspan="4">&nbsp;</TD> 
					</TR>
					<TR> 
						<TD bgcolor="#DDDDFF" valign="top"><B><? echo _T("Address:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE" colspan="3"><? echo $adresse_adh; ?>&nbsp;<BR><? echo $adresse2_adh; ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Zip Code:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $cp_adh; ?>&nbsp;</TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("City:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $ville_adh; ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Country:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $pays_adh; ?>&nbsp;</TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Phone:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $tel_adh; ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Mobile phone:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $gsm_adh; ?>&nbsp;</TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("E-Mail:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($email_adh!="") { ?><A href="mailto:<? echo $email_adh; ?>"><? echo $email_adh; ?></A><? } ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Website:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($url_adh!="") { ?><A href="<? echo $url_adh; ?>"><? echo $url_adh; ?></A><? } ?>&nbsp;</TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("ICQ:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $icq_adh; ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Jabber:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $jabber_adh; ?>&nbsp;</TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("MSN:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? if ($msn_adh!="") { ?><A href="mailto:<? echo $msn_adh; ?>"><? echo $msn_adh; ?></A><? } ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Id GNUpg (GPG):"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $gpgid; ?>&nbsp;</TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("fingerprint:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $fingerprint; ?>&nbsp;</TD> 
					</TR> 
					<TR> 
						<TD colspan="4">&nbsp;</TD> 
					</TR>
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Username:"); ?>&nbsp;</B></TD> 
						<TD bgcolor="#EEEEEE"><? echo $login_adh; ?></TD> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Password:"); ?></B>&nbsp;</TD> 
						<TD bgcolor="#EEEEEE"><? echo $mdp_adh; ?></TD> 
					</TR> 
<?
			$ajout_contrib="";
			if ($_SESSION["admin_status"]!=0)
			{
?>
					<TR> 
						<TD bgcolor="#DDDDFF"><B><? echo _T("Creation date:"); ?></B>&nbsp;</TD> 
						<TD bgcolor="#EEEEEE" colspan="3"><? echo $date_crea_adh; ?></TD> 
					</TR> 
					<TR> 
						<TD bgcolor="#DDDDFF" valign="top"><B><? echo _T("Other informations (admin):"); ?></B></TD> 
						<TD bgcolor="#EEEEEE" colspan="3"><? echo nl2br($info_adh); ?></TD> 
					</TR>
<?
				$ajout_contrib = "&nbsp;&nbsp;&nbsp;<A href=\"ajouter_contribution.php?id_adh=".$id_adh."\">"._T("[ Add a contribution ]")."</A>";
			}
?>
					<TR> 
						<TD bgcolor="#DDDDFF" valign="top"><B><? echo _T("Other informations:"); ?></B></TD> 
						<TD bgcolor="#EEEEEE" colspan="3"><? echo nl2br($info_public_adh); ?></TD> 
					</TR>
<?
    $requete = "SELECT id_cat, index_cat, name_cat, perm_cat, type_cat, size_cat FROM $info_cat_table";
    if ($_SESSION["admin_status"] != 1)
       $requete .= " WHERE perm_cat=$perm_all";
    $requete .= " ORDER BY index_cat";
    $res_cat = $DB->Execute($requete);
    while (!$res_cat->EOF)
    {
        $id_cat = $res_cat->fields[0];
        $rank_cat = $res_cat->fields[1];
        $name_cat = $res_cat->fields[2];
        $perm_cat = $res_cat->fields[3];
        $type_cat = $res_cat->fields[4];
        $size_cat = $res_cat->fields[5];

        if ($type_cat == $category_separator) {
            for ($i = 0; $i < $size_cat; ++$i) {
?>                                                
                                                    <TR><TH colspan="4" id="header">&nbsp;</TH></TR> 
<?
            }
        } else {
            $res_info = $DB->Execute("SELECT val_info, index_info FROM ".PREFIX_DB."adh_info WHERE id_cat=$id_cat and id_adh=$id_adh ORDER BY index_info");
            $current_size = $size_cat;
            if ($size_cat == 0)
                $current_size = $res_info->RecordCount();
            for ($i = 0; $i < $current_size; ++$i) {
?> 
                                                <TR>
<?
                if ($i == 0) {
?> 
                                                    <TD rowspan="<?php echo $current_size; ?>" bgcolor="#DDDDFF" valign="top"><B><?php echo $name_cat."&nbsp;:"; ?></B></TD> 
<?
                }
                $val = $res_info->EOF ? "&nbsp;" : htmlspecialchars($res_info->fields[0]);
                if ($type_cat == $category_text)
                    $val = nl2br($val);
?> 
                                                    <TD bgcolor="#EEEEEE" colspan="3"><? echo $val; ?></TD> 
                                                </TR>
<?
                $res_info->MoveNext();
            }
            $res_info->Close();
        }
        $res_cat->MoveNext();
    }
    $res_cat->Close();
?>
					<TR>
						<TD colspan="4" align="center"><BR><A href="ajouter_adherent.php?id_adh=<? echo $id_adh; ?>"><? echo _T("[ Modification ]"); ?></A>&nbsp;&nbsp;&nbsp;<A href="gestion_contributions.php?id_adh=<? echo $id_adh; ?>"><? echo _T("[ Contributions ]"); ?></A><? echo $ajout_contrib; ?></TD>
					</TR>
                                </TABLE> 
			</DIV>
			<BR> 
		</BLOCKQUOTE> 			
<? 
  include("footer.php") 
  */
?>
