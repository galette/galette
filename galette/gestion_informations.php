<?
 
/* gestion_informations.php
 * - Page de modification des informations (pour l'adhérent)
 * Copyright (c) 2003 Frédéric Jacquot
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
	include(WEB_ROOT."includes/functions.inc.php"); 
	include(WEB_ROOT."includes/lang.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	else
		$id_adh = $_SESSION["logged_id_adh"];

  	if ($id_adh!="")
  	{
  		$requete = "SELECT nom_adh, prenom_adh FROM ".PREFIX_DB."adherents WHERE id_adh=".$DB->qstr($id_adh);
		$resultat = $DB->Execute($requete);
		if (!$resultat->EOF)
		{
			$nom_adh = $resultat->fields[0];
			$prenom_adh = $resultat->fields[1];
			$resultat->Close();
		}
  	}
		
	// variables d'erreur (pour affichage)	    
 	$error_detected = "";
 	$warning_detected = "";

	    
	  //
	 // DEBUT parametrage des champs
  //  On recupere de la base la longueur et les flags des champs
  //  et on initialise des valeurs par defaut
    
  // recuperation de la liste de champs de la table
  $fields = &$DB->MetaColumns(PREFIX_DB."adherents");
  while (list($champ, $proprietes) = each($fields))
	{
		$proprietes_arr = get_object_vars($proprietes);
		// on obtient name, max_length, type, not_null, has_default, primary_key,
		// auto_increment et binary		
		
		$fieldname = $proprietes_arr["name"];
		$fieldreq = $fieldname."_req";
		$fieldlen = $fieldname."_len";
	
	  // definissons  aussi la longueur des input text
	  $max_tmp = $proprietes_arr["max_length"];
	  if ($max_tmp == "-1")
	  	$max_tmp = 10;
	  $$fieldlen=$max_tmp;	

	  // et s'ils sont obligatoires (à partir de la base)
	  if ($proprietes_arr["not_null"]==1)
	    $$fieldreq = "style=\"color: #FF0000;\"";
	  else
	    $$fieldreq = "";
	}
	reset($fields);

	  //
	 // FIN parametrage des champs
	// 	    	    
    
    //
   // Validation du formulaire
  //
  
  if (isset($_POST["valid"]))
  {
  	// verification de champs
  	$update_string = "";
  	$insert_string_fields = "";
  	$insert_string_values = "";
  
  	// recuperation de la liste de champs de la table
	  while (list($champ, $proprietes) = each($fields))
		{
			$proprietes_arr = get_object_vars($proprietes);
			// on obtient name, max_length, type, not_null, has_default, primary_key,
			// auto_increment et binary		
		
			$fieldname = $proprietes_arr["name"];
			$fieldreq = $fieldname."_req";
			
			// on ne met jamais a jour date_crea_adh, id_adh, titre_adh, nom_adh
			// prenom_adh, id_statut, id_societe_adh, activite_adh, bool_admin_adh
			if ($fieldname!="date_crea_adh" && 
					$fieldname!="id_adh"  && 
					$fieldname!="titre_adh" && 
					$fieldname!="id_statut" &&
					$fieldname!="activite_adh" &&
					$fieldname!="bool_exempt_adh" &&
					$fieldname!="bool_admin_adh" &&
					$fieldname!="date_echeance" &&
					$fieldname!="info_adh")
			{			
				if (isset($_POST[$fieldname]))
				  $post_value=trim($_POST[$fieldname]);
				else			
				  $post_value="";

				// on declare les variables pour la présaisie en cas d'erreur
				$$fieldname = htmlentities(stripslashes($post_value),ENT_QUOTES);

				// vérification de la présence des champs obligatoires
				if ($$fieldreq!="" && $post_value=="")
				  $error_detected .= "<LI>"._T("- Champ obligatoire non renseigné.")."</LI>";
				else
				{
					// validation des dates				
					if($proprietes_arr["type"]=="date" && $post_value!="")
					{
					  if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $post_value, $array_jours) || $post_value=="")
					  {
						  if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]) || $post_value=="")
								$value=$DB->DBDate(mktime(0,0,0,$array_jours[2],$array_jours[1],$array_jours[3]));
							else
								$error_detected .= "<LI>"._T("- Date non valide !")."</LI>";
					  }
					  else
					  	$error_detected .= "<LI>"._T("- Mauvais format de date (jj/mm/aaaa) !")."</LI>";
					}
					elseif ($fieldname=="email_adh")
 					{
 						$post_value=strtolower($post_value);
						if (!is_valid_email($post_value) && $post_value!="")
					  	$error_detected .= "<LI>"._T("- Adresse E-mail non valide !")."</LI>";
						else
		 					$value = $DB->qstr($post_value, true);
					}
					elseif ($fieldname=="url_adh")
 					{
 						if (!is_valid_web_url($post_value) && $post_value!="" && $post_value!="http://")
					  	$error_detected .= "<LI>"._T("- Adresse web non valide ! Oubli du http:// ?")."</LI>";
						else
						{
							if ($post_value=="http://")
								$post_value="";
		 					$value = $DB->qstr($post_value, true);
						}
					}
 					elseif ($fieldname=="login_adh")
 					{
 						if (strlen($post_value)<4)
 							$error_detected .= "<LI>"._T("- L'identifiant doit être composé d'au moins 4 caractères !")."</LI>";
 						else
 						{
 							// on vérifie que le login n'est pas déjà utilisé
 							$requete = "SELECT id_adh
 													FROM ".PREFIX_DB."adherents
 													WHERE login_adh=" . $DB->qstr($post_value) . " ";
 							if ($id_adh!="")
 								$requete .= "AND id_adh!=" . $DB->qstr($id_adh);
 															
 							$result = &$DB->Execute($requete);
							if (!$result->EOF || $post_value==PREF_ADMIN_LOGIN)
	 							$error_detected .= "<LI>"._T("- Cet identifiant est déjà utilisé par un autre adhérent !")."</LI>";
							else
	 							$value = $DB->qstr($post_value, true);
						}
 					}
 					elseif ($fieldname=="mdp_adh")
 					{
 						if (strlen($post_value)<4)
 							$error_detected .= "<LI>"._T("- Le mot de passe doit être composé d'au moins 4 caractères !")."</LI>";
 						else
 							$value = $DB->qstr($post_value, true);
 					}
 					else
 					{
 						// on se contente d'escaper le html et les caracteres speciaux
							$value = $DB->qstr($post_value, true);
					}

					// mise à jour des chaines d'insertion/update
					if ($value=="''")
						$value="NULL";
					
					// aucune chance d'update nom ou prenom
					if ($fieldname!="nom_adh" &&
							$fieldname!="prenom_adh")
					{
						$update_string .= ",".$fieldname."=".$value;
						$insert_string_fields .= ",".$fieldname;
						$insert_string_values .= ",".$value;		
					}
				}
			}
		}
		reset($fields);
  
  	// modif ou ajout
  	if ($error_detected=="")
  	{  	 		 		
 		 	$requete = "UPDATE ".PREFIX_DB."adherents
 		 								SET " . substr($update_string,1) . " 
 		 								WHERE id_adh=" . $id_adh;
			$DB->Execute($requete);
			dblog(_T("Mise à jour de la fiche adhérent :")." ".strtoupper($nom_adh)." ".$prenom_adh, $requete);
  				if (isset($_FILES["photo"]["tmp_name"]))
                        if ($_FILES["photo"]["tmp_name"]!="none" &&
                            $_FILES["photo"]["tmp_name"]!="") 
			{ 
				if ($_FILES['photo']['type']=="image/jpeg" || 
				    (function_exists("ImageCreateFromGif") && $_FILES['photo']['type']=="image/gif") || 
				    $_FILES['photo']['type']=="image/png" ||
				    $_FILES['photo']['type']=="image/x-png")
				{
					$tmp_name = $HTTP_POST_FILES["photo"]["tmp_name"];
						
					// extension du fichier (en fonction du type mime)
					if ($_FILES['photo']['type']=="image/jpeg")
						$ext_image = ".jpg";
					if ($_FILES['photo']['type']=="image/png" || $_FILES['photo']['type']=="image/x-png")
						$ext_image = ".png";
					if ($_FILES['photo']['type']=="image/gif")
						$ext_image = ".gif";
						
					// suppression ancienne photo
					// NB : une verification sur le type de $id_adh permet d'eviter une faille
					//      du style $id_adh = "../../../image"
					@unlink(WEB_ROOT . "photos/".$id_adh.".jpg");
					@unlink(WEB_ROOT . "photos/".$id_adh.".gif");
					@unlink(WEB_ROOT . "photos/".$id_adh.".jpg");
					@unlink(WEB_ROOT . "photos/tn_".$id_adh.".jpg");
					@unlink(WEB_ROOT . "photos/tn_".$id_adh.".gif");
					@unlink(WEB_ROOT . "photos/tn_".$id_adh.".jpg");
						
					// copie fichier temporaire				 		
					if (!@move_uploaded_file($tmp_name,WEB_ROOT . "photos/".$id_adh.$ext_image))
						$warning_detected .= "<LI>"._T("- La photo semble ne pas avoir été transmise correstement. L'enregistrement a cependant été effectué.")."</LI>";
				 	else
						resizeimage(WEB_ROOT . "photos/".$id_adh.$ext_image,WEB_ROOT . "photos/tn_".$id_adh.$ext_image,130,130);
			 	}
			 	else
				{
					if (function_exists("ImageCreateFromGif"))
			 			$warning_detected .= "<LI>"._T("- Le fichier transmis n'est pas une image valide (GIF, PNG ou JPEG). L'enregistrement a cependant été effectué.")."</LI>"; 
					else
			 			$warning_detected .= "<LI>"._T("- Le fichier transmis n'est pas une image valide (PNG ou JPEG). L'enregistrement a cependant été effectué.")."</LI>"; 
				}
			}
		} 
		if ($warning_detected=="" && $error_detected=="")
			header("location: voir_adherent.php");
  }
  
 	// suppression photo
	if (isset($_POST["del_photo"]))
  {
 		@unlink(WEB_ROOT . "photos/" . $id_adh . ".jpg");
 		@unlink(WEB_ROOT . "photos/" . $id_adh . ".png");
 		@unlink(WEB_ROOT . "photos/" . $id_adh . ".gif");
 		@unlink(WEB_ROOT . "photos/tn_" . $id_adh . ".jpg");
 		@unlink(WEB_ROOT . "photos/tn_" . $id_adh . ".png");
 		@unlink(WEB_ROOT . "photos/tn_" . $id_adh . ".gif");
 	} 	
	
	  //	
	 // Pré-remplissage des champs
	//  avec des valeurs issues de la base
	//  -> donc uniquement si l'enregistrement existe et que le formulaire
	//     n'a pas déja été posté avec des erreurs (pour pouvoir corriger)
	
	if (!isset($_POST["valid"]) || (isset($_POST["valid"]) && $error_detected==""))
	{
		// recup des données
		$requete = "SELECT * 
			    FROM ".PREFIX_DB."adherents 
			    WHERE id_adh=$id_adh";
		$result = &$DB->Execute($requete);
		
		// recuperation de la liste de champs de la table
	  //$fields = &$DB->MetaColumns(PREFIX_DB."cotisations");
	  while (list($champ, $proprietes) = each($fields))
		{
			$val="";
			$proprietes_arr = get_object_vars($proprietes);
			// on obtient name, max_length, type, not_null, has_default, primary_key,
			// auto_increment et binary		
		
		  // déclaration des variables correspondant aux champs
		  // et reformatage des dates.
			
			// on doit faire cette verif pour une enventuelle valeur "NULL"
			// non renvoyée -> ex: pas de societe membre
			// sinon on obtient un warning
			if (isset($result->fields[$proprietes_arr["name"]]))
				$val = $result->fields[$proprietes_arr["name"]];

			if($proprietes_arr["type"]=="date" && $val!="")
			{
			  list($a,$m,$j)=split("-",$val);
			  $val="$j/$m/$a";
			}
		  	$$proprietes_arr["name"] = htmlentities(stripslashes(addslashes($val)), ENT_QUOTES);
		}
		reset($fields);
	}

	if ($url_adh=="")
		$url_adh = "http://";

	include("header.php");

?> 
 
						<H1 class="titre"><? echo _T("Fiche adhérent (modification)"); ?></H1>
						<FORM action="gestion_informations.php" method="post" enctype="multipart/form-data"> 
						
<?
	// Affichage des erreurs
	if ($error_detected!="")
	{
?>
  	<DIV id="errorbox">
  		<H1><? echo _T("- ERREUR -"); ?></H1>
  		<UL>
  			<? echo $error_detected; ?>
  		</UL>
  	</DIV>
<?
	}
	if ($warning_detected!="")
	{
?>
	<DIV id="warningbox">
  		<H1><? echo _T("- AVERTISSEMENT -"); ?></H1>
  		<UL>
  			<? echo $warning_detected; ?>
  		</UL>
  	</DIV>
<?
	}
?>						
						<BLOCKQUOTE>
						<div align="center">
						<table border="0" id="input-table"> 
							<tr> 
							<TH <? echo $nom_adh_req ?> id="libelle"><? echo _T("Nom :"); ?></TH> 
									<td><input type="text" name="nom_adh" value="<? echo $nom_adh; ?>" maxlength="<? echo $nom_adh_len; ?>" disabled></td> 
								<td colspan="2" rowspan="5" align="center" width="100">
								<?
									$image_adh = "";
									if (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".jpg"))
										$image_adh = "photos/tn_" . $id_adh . ".jpg";
									elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".gif"))
										$image_adh = "photos/tn_" . $id_adh . ".gif";
									elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".png"))
										$image_adh = "photos/tn_" . $id_adh . ".png";
									elseif (file_exists(WEB_ROOT . "photos/" . $id_adh . ".jpg"))
										$image_adh = "photos/" . $id_adh . ".jpg";
									elseif (file_exists(WEB_ROOT . "photos/" . $id_adh . ".gif"))
										$image_adh = "photos/" . $id_adh . ".gif";
									elseif (file_exists(WEB_ROOT . "photos/" . $id_adh . ".png"))
										$image_adh = "photos/" . $id_adh . ".png";
									
									if ($image_adh != "")
									{
										if (function_exists("ImageCreateFromString"))
											$imagedata = getimagesize($image_adh);
										else
											$imagedata = array("130","");
								?>
									<img src="<? echo $image_adh ?>" border="1" alt="<? echo _T("Photo"); ?>" width="<? echo $imagedata[0]; ?>" height="<? echo $imagedata[1]; ?>">
								<?
									}
									else
										echo _T("[ pas de photo ]");
								?>	
								</td>
						  </tr>
						  <TR>
								<TH <? echo $prenom_adh_req ?> id="libelle"><? echo _T("Prénom :"); ?></TH> 
								<TD><INPUT type="text" name="prenom_adh" value="<? echo $prenom_adh; ?>" maxlength="<? echo $prenom_adh_len; ?>"></TD> 
							</TR>						   
							<TR> 
								<TH <? echo $pseudo_adh_req ?> id="libelle"><? echo _T("Pseudo :"); ?></TH> 
								<TD><INPUT type="text" name="pseudo_adh" value="<? echo $pseudo_adh; ?>" maxlength="<? echo $pseudo_adh_len; ?>"></TD> 
						  </TR> 
							<TR> 
								<TH <? echo $ddn_adh_req ?> id="libelle"><? echo _T("Date de naissance :"); ?><br>&nbsp;</TH> 
								<TD><INPUT type="text" name="ddn_adh" value="<? echo $ddn_adh; ?>" maxlength="10"><BR><DIV class="exemple"><? echo _T("(format jj/mm/aaaa)"); ?></DIV></TD>
							</TR>
							<TR>
							  <TH <? echo $prof_adh_req ?> id="libelle"><? echo _T("Profession :"); ?></TH> 
								<TD><input type="text" name="prof_adh" value="<? echo $prof_adh; ?>" maxlength="<? echo $prof_adh_len; ?>"></TD> 
							</TR> 
							<tr>
								<th id="header" colspan="2">&nbsp;</td> 
								<TH id="libelle"><? echo _T("Photo :"); ?></TH> 
								<td> 
								<?
									if (file_exists(WEB_ROOT . "photos/" . $id_adh . ".jpg") ||
											file_exists(WEB_ROOT . "photos/" . $id_adh . ".png") ||
											file_exists(WEB_ROOT . "photos/" . $id_adh . ".gif"))
									{
								?>
									<input type="submit" name="del_photo" value="<? echo _T("Supprimer la photo"); ?>">
								<?
									}
									else
									{
								?>
									<input type="file" name="photo"><br>
								<?
									}
								?>
								</td> 
							</tr>
							<TR> 
								<TH colspan="4" id="header">&nbsp;</TH> 
							</TR>
							<tr> 
								<TH id="libelle" <? echo $adresse_adh_req ?>><? echo _T("Adresse :"); ?></TH> 
								<td colspan="3">
									<input type="text" name="adresse_adh" value="<? echo $adresse_adh; ?>" maxlength="<? echo $adresse_adh_len; ?>" size="63">
								</td> 
						  </tr> 
							<tr> 
								<TH id="libelle">&nbsp;</TH> 
								<td colspan="3">
									<input type="text" name="adresse2_adh" value="<? echo $adresse2_adh; ?>" maxlength="<? echo $adresse2_adh_len; ?>" size="63">
								</td> 
						  </tr> 
							<TR> 
								<TH id="libelle" <? echo $cp_adh_req ?>><? echo _T("Code Postal :"); ?></TH> 
								<TD><INPUT type="text" name="cp_adh" value="<? echo $cp_adh; ?>" maxlength="<? echo $cp_adh_len; ?>"></TD> 
								<TH id="libelle" <? echo $ville_adh_req ?>><? echo _T("Ville :"); ?></TH> 
								<TD><INPUT type="text" name="ville_adh" value="<? echo $ville_adh; ?>" maxlength="<? echo $ville_adh_len; ?>"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $pays_adh_req ?>><? echo _T("Pays :"); ?></TH> 
								<TD><INPUT type="text" name="pays_adh" value="<? echo $pays_adh; ?>" maxlength="<? echo $pays_adh_len; ?>"></TD> 
								<TH id="libelle" <? echo $tel_adh_req ?>><? echo _T("Tel :"); ?></TH> 
								<TD><INPUT type="text" name="tel_adh" value="<? echo $tel_adh; ?>" maxlength="<? echo $tel_adh_len; ?>"></TD> 
						  </TR>  
							<TR> 
								<TH id="libelle" <? echo $gsm_adh_req ?>><? echo _T("GSM :"); ?></TH> 
								<TD><INPUT type="text" name="gsm_adh" value="<? echo $gsm_adh; ?>" maxlength="<? echo $gsm_adh_len; ?>"></TD> 
								<TH id="libelle" <? echo $email_adh_req ?>><? echo _T("E-Mail :"); ?></TH> 
								<TD><INPUT type="text" name="email_adh" value="<? echo $email_adh; ?>" maxlength="<? echo $email_adh_len; ?>" size="30"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $url_adh_req ?>><? echo _T("Site Web :"); ?></TH> 
								<TD><INPUT type="text" name="url_adh" value="<? echo $url_adh; ?>" maxlength="<? echo $url_adh_len; ?>" size="30"></TD> 
								<TH id="libelle" <? echo $icq_adh_req ?>><? echo _T("ICQ :"); ?></TH> 
								<TD><INPUT type="text" name="icq_adh" value="<? echo $icq_adh; ?>" maxlength="<? echo $icq_adh_len; ?>"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $jabber_adh_req ?>><? echo _T("Jabber :"); ?></TH> 
								<TD><INPUT type="text" name="jabber_adh" value="<? echo $jabber_adh; ?>" maxlength="<? echo $jabber_adh_len; ?>" size="30"></TD> 
								<TH id="libelle" <? echo $msn_adh_req ?>><? echo _T("MSN :"); ?></TH> 
								<TD><INPUT type="text" name="msn_adh" value="<? echo $msn_adh; ?>" maxlength="<? echo $msn_adh_len; ?>" size="30"></TD> 
						  </TR> 
							<TR> 
								<TH colspan="4" id="header">&nbsp;</TH> 
							</TR>
							<TR> 
								<TH id="libelle" <? echo $login_adh_req ?>><? echo _T("Identifiant :"); ?><BR>&nbsp;</TH> 
								<TD><INPUT type="text" name="login_adh" value="<? echo $login_adh; ?>" maxlength="<? echo $login_adh_len; ?>"><BR><DIV class="exemple"><? echo _T("(au moins 4 caractères)"); ?></DIV></TD> 
								<TH id="libelle" <? echo $mdp_adh_req ?>><? echo _T("Mot de passe :"); ?><BR>&nbsp;</TH> 
								<TD><INPUT type="text" name="mdp_adh" value="<? echo $mdp_adh; ?>" maxlength="<? echo $mdp_adh_len; ?>"><BR><DIV class="exemple"><? echo _T("(au moins 4 caractères)"); ?></DIV></TD> 
						  </TR>  
							<tr> 
								<TH id="libelle" <? echo $info_public_adh_req ?>><? echo _T("Autres informations :"); ?></TH> 
								<td colspan="3"><textarea name="info_public_adh" cols="61" rows="6"><? echo $info_public_adh; ?></textarea></td> 
						 	</tr> 
							<tr> 
								<TH align="center" colspan="4"><BR><INPUT type="submit" name="valid" value="<? echo _T("Enregistrer"); ?>"></TH> 
							</tr> 
							</table> 
						</div>
						<br> 
						<? echo _T("NB : Les champs obligatoires apparaissent en"); ?> <font style="color: #FF0000"><? echo _T("rouge"); ?></font>. 
						</BLOCKQUOTE> 
						<INPUT type="hidden" name="nom_adh" value="<? echo $nom_adh ?>">
						<INPUT type="hidden" name="prenom_adh" value="<? echo $prenom_adh ?>">
						</FORM> 
<? 
	// } 
 
 
  include("footer.php") 
?>
