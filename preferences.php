<? 
/* preferences.php
 * - Preferences Galette
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
	include(WEB_ROOT."includes/functions.inc.php"); 
	include(WEB_ROOT."includes/lang.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 

	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: gestion_informations.php");

	// On vérifie si on a une référence => modif ou création

	// variables d'erreur (pour affichage)	    
 	$error_detected = "";
 	$warning_detected = "";

	 //
	// DEBUT parametrage des champs
	//  On recupere de la base la longueur et les flags des champs
	//   et on initialise des valeurs par defaut
    
	// recuperation de la liste de champs de la table
	$fields = &$DB->MetaColumns("preferences");
	while (list($champ, $proprietes) = each($fields))
	{
		$proprietes_arr = get_object_vars($proprietes);
		// on obtient name, max_length, type, not_null, has_default, primary_key,
		// auto_increment et binary		
		
		$fieldname = $proprietes_arr["name"];
				
		// on ne met jamais a jour id_adh
		if ($fieldname!="id_adh" && $fieldname!="date_echeance")
			eval("\$".$fieldname." = \"\";");

	  // definissons  aussi la longueur des input text
	  $max_tmp = $proprietes_arr["max_length"];
	  if ($max_tmp == "-1")
	  	$max_tmp = 10;
	  eval("\$".$fieldname."_len = ".$max_tmp.";");

	  // et s'ils sont obligatoires (à partir de la base)
	  if ($proprietes_arr["not_null"]==1)
		  eval("\$".$fieldname."_req = \" style=\\\"color: #FF0000;\\\"\";");
		else
		  eval("\$".$fieldname."_req = \"\";");
	}
	reset($fields);
	
	/*
	// et les valeurs par defaut
	$id_statut = "4";
	$titre_adh = "1";
	*/

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
				
				if (isset($_POST[$fieldname]))
				  $post_value=trim($_POST[$fieldname]);
				else			
					$post_value="";
					
				// on declare les variables pour la présaisie en cas d'erreur
				eval("\$".$fieldname." = htmlentities(stripslashes(\"".$post_value."\"),ENT_QUOTES);");				

				// vérification de la présence des champs obligatoires
				eval("\$req = \$" . $fieldname . "_req;");
				if ($req!="" && $post_value=="")
				  $error_detected .= "<LI>"._T("- Champ obligatoire non renseigné.")."</LI>";
				else
				{
					// validation de la langue
					if ($fieldname=="pref_lang")
 					{
 						if (file_exists(WEB_ROOT . "lang/lang_" . $post_value . ".php"))
		 					$value = $DB->qstr($post_value, true);
		 				else
					  		$error_detected .= "<LI>"._T("- Langue non valide !")."</LI>";
					}
					// validation des dates				
					elseif ($fieldname=="pref_email")
 					{
 						$post_value=strtolower($post_value);
						if (!is_valid_email($post_value) && $post_value!="")
					  	$error_detected .= "<LI>"._T("- Adresse E-mail non valide !")."</LI>";
						else
		 					$value = $DB->qstr($post_value, true);
					}
  					elseif ($fieldname=="pref_admin_login")
 					{
 						if (strlen($post_value)<4)
 							$error_detected .= "<LI>"._T("- L'identifiant doit être composé d'au moins 4 caractères !")."</LI>";
 						else
 						{
 							// on vérifie que le login n'est pas déjà utilisé
 							$requete = "SELECT id_adh
 								    FROM adherents
 								    WHERE login_adh=". $DB->qstr($post_value, true);
 							if ($id_adh!="")
 								$requete .= " AND id_adh!=" . $DB->qstr($id_adh, true);

 							$result = &$DB->Execute($requete);
							if (!$result->EOF)
	 							$error_detected .= "<LI>"._T("- Cet identifiant est déjà utilisé par un adhérent !")."</LI>";
							else
	 							$value = $DB->qstr($post_value, true);
						}
 					}
 					elseif(strstr($proprietes_arr["type"],"int"))
 					{
 						// évitons la divison par zero
 						if ($fieldname=="pref_numrows" && $post_value=="0")
 							$post_value="1";
 					
 						if ((is_numeric($post_value) && $post_value >=0) || $post_value=="")
						  $value=$DB->qstr($post_value,ENT_QUOTES);
						else
							$error_detected .= "<LI>"._T("- Les nombres et mesures doivent être des entiers !")."</LI>";
 					}
 					elseif ($fieldname=="pref_admin_pass")
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
					$update_string .= ",".$fieldname."=".$value;
					$insert_string_fields .= ",".$fieldname;
					$insert_string_values .= ",".$value;		
			}
		}
		reset($fields);
  
  	// modif ou ajout
  	if ($error_detected=="")
  	{  	
			$requete = "DELETE FROM preferences";
			$DB->Execute($requete);
  			$requete = "INSERT INTO preferences
  									(" . substr($insert_string_fields,1) . ") 
  									VALUES (" . substr($insert_string_values,1) . ")";
			$DB->Execute($requete);
 			dblog(_T("Mise à jour des préférences"),$requete);							
							
			// récupération du max pour insertion photo
			// ou passage en mode modif apres insertion

			if (isset($_FILES["photo"]["tmp_name"]))
                        if ($_FILES["photo"]["tmp_name"]!="none" &&
                            $_FILES["photo"]["tmp_name"]!="") 
			{ 

				if ($_FILES['photo']['type']=="image/jpeg" || 
				    $_FILES['photo']['type']=="image/gif" || 
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
					@unlink(WEB_ROOT . "photos/logo.jpg");
					@unlink(WEB_ROOT . "photos/logo.gif");
					@unlink(WEB_ROOT . "photos/logo.jpg");
						
					// copie fichier temporaire				 		
					if (!@move_uploaded_file($tmp_name,WEB_ROOT . "photos/logo".$ext_image))
					$warning_detected .= "<LI>"._T("- La photo semble ne pas avoir été transmise correstement. L'enregistrement a cependant été effectué.")."</LI>";
				 		
			 	}
			 	else
			 		$warning_detected .= "<LI>"._T("- Le fichier transmis n'est pas une image valide (GIF, PNG ou JPEG). L'enregistrement a cependant été effectué.")."</LI>"; 
			}
			
			// retour à l'accueil
			if ($warning_detected=="")
			{
				header("location: index.php");
				die();
			}
		}  	
	}
  
 	// suppression photo
	if (isset($_POST["del_photo"]))
  {
 		@unlink(WEB_ROOT . "photos/logo.jpg");
 		@unlink(WEB_ROOT . "photos/logo.png");
 		@unlink(WEB_ROOT . "photos/logo.gif");
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
								FROM preferences";
		$result = &$DB->Execute($requete);
        	if ($result->EOF)
	                header("location: index.php");
			                                                                                                                    
																	    
			
		// recuperation de la liste de champs de la table
	  //$fields = &$DB->MetaColumns("cotisations");
	  while (list($champ, $proprietes) = each($fields))
		{
			//echo $proprietes_arr["name"]." -- (".$result->fields[$proprietes_arr["name"]].")<br>";

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
		  eval("\$".$proprietes_arr["name"]." = htmlentities(stripslashes(\"".addslashes($val)."\"), ENT_QUOTES);");
		}
		reset($fields);
	}
	else
	{
		// initialisation des champs
			
	}

	include("header.php");

?> 
 
						<H1 class="titre"><? echo _T("Préférences"); ?></H1>
						<FORM action="preferences.php" method="post" enctype="multipart/form-data"> 
						
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
						<DIV align="center">

						<TABLE border="0" id="input-table">
							<TR>
								<TH colspan="2" id="header"><? echo _T("Informations générales :"); ?></TH>
							</TR> 
							<TR> 
								<TH <? echo $pref_nom_req ?> id="libelle"><? echo _T("Nom (raison sociale) de l'association :"); ?></TH> 
								<TD><INPUT type="text" name="pref_nom" value="<? echo $pref_nom; ?>" maxlength="<? echo $pref_nom_len; ?>"></TD>
							</TR>
							<TR>
								<TH id="libelle"><? echo _T("Logo :"); ?></TH> 
								<td>
								<?
									$logo_asso = "";
									if (file_exists(WEB_ROOT . "photos/logo.jpg"))
										$logo_asso = "photos/logo.jpg";
									if (file_exists(WEB_ROOT . "photos/logo.gif"))
										$logo_asso = "photos/logo.gif";
									if (file_exists(WEB_ROOT . "photos/logo.png"))
										$logo_asso = "photos/logo.png";
									
									if ($logo_asso != "")
									{
								?>
									<img src="<? echo $logo_asso."?nocache=".time(); ?>" border="1" alt="<? echo _T("Photo"); ?>" width="100"><BR>
									<input type="submit" name="del_photo" value="<? echo _T("Supprimer la photo"); ?>">
								<?
									}
									else
									{
								?>
										<input type="file" name="photo">
								<?
									}
								?>	
								</td>
						  </TR>
						  <TR>
								<TH<? echo $pref_adresse_req ?> id="libelle"><? echo _T("Adresse :"); ?></TH> 
								<td><input type="text" name="pref_adresse" value="<? echo $pref_adresse; ?>" maxlength="<? echo $pref_adresse_len; ?>" size="42"></td> 
							</TR>						   
						  <TR>
								<TH id="libelle">&nbsp;</TH> 
								<td><input type="text" name="pref_adresse2" value="<? echo $pref_adresse2; ?>" maxlength="<? echo $pref_adresse2_len; ?>" size="42"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_cp_req ?> id="libelle"><? echo _T("Code Postal :"); ?></TH> 
								<td><input type="text" name="pref_cp" value="<? echo $pref_cp; ?>" maxlength="<? echo $pref_cp_len; ?>"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_ville_req ?> id="libelle"><? echo _T("Ville :"); ?></TH> 
								<td><input type="text" name="pref_ville" value="<? echo $pref_ville; ?>" maxlength="<? echo $pref_ville_len; ?>"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_pays_req ?> id="libelle"><? echo _T("Pays :"); ?></TH> 
								<td><input type="text" name="pref_pays" value="<? echo $pref_pays; ?>" maxlength="<? echo $pref_pays_len; ?>"></td> 
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _T("Paramètres galette :"); ?></TH>
							</TR> 
						  <TR>
								<TH<? echo $pref_lang_req ?> id="libelle"><? echo _T("Langue :"); ?></TH>
								<TD>
									<SELECT name="pref_lang">
<?
	$path = WEB_ROOT."/lang";
	$dir_handle = @opendir($path);
	while ($file = readdir($dir_handle))
	{
		if (substr($file,0,5)=="lang_" && substr($file,-4)==".php")
		{
        $file = substr(substr($file,5),0,-4);
?>
										<OPTION value="<? echo $file; ?>" <? isSelected($file,$pref_lang) ?>><? echo ucfirst($file); ?></OPTION>
<?
		}
	}
	closedir($dir_handle);
?>
									</SELECT>
								</TD> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_numrows_req ?> id="libelle"><? echo _T("Lignes / Page :"); ?></TH> 
								<td><input type="text" name="pref_numrows" value="<? echo $pref_numrows; ?>" maxlength="<? echo $pref_numrows_len; ?>"> <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_log_req ?> id="libelle"><? echo _T("Niveau d'historique :"); ?></TH> 
								<TD>
									<SELECT name="pref_log">
										<OPTION value="0" <? isSelected("0",$pref_log) ?>><? echo _T("Nul"); ?></OPTION>
										<OPTION value="1" <? isSelected("1",$pref_log) ?>><? echo _T("Normal"); ?></OPTION>
										<OPTION value="2" <? isSelected("2",$pref_log) ?>><? echo _T("Détaillé"); ?></OPTION>
									</SELECT>
								</TD>
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _T("Paramètres mail :"); ?></TH>
							</TR> 
						  <TR>
								<TH<? echo $pref_email_nom_req ?> id="libelle"><? echo _T("Nom expéditeur :"); ?></TH> 
								<td><input type="text" name="pref_email_nom" value="<? echo $pref_email_nom; ?>" maxlength="<? echo $pref_email_nom_len; ?>"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_email_req ?> id="libelle"><? echo _T("Email expéditeur :"); ?></TH> 
								<td><input type="text" name="pref_email" value="<? echo $pref_email; ?>" maxlength="<? echo $pref_email_len; ?>" size="30"></td> 
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _T("Paramètres de génération d'étiquettes :"); ?></TH>
							</TR> 
						  <TR>
								<TH<? echo $pref_etiq_marges_req ?> id="libelle"><? echo _T("Marges :"); ?></TH> 
								<td><input type="text" name="pref_etiq_marges" value="<? echo $pref_etiq_marges; ?>" maxlength="<? echo $pref_etiq_marges_len; ?>"> mm <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_hspace_req ?> id="libelle"><? echo _T("Espacement horizontal :"); ?></TH> 
								<td><input type="text" name="pref_etiq_hspace" value="<? echo $pref_etiq_hspace; ?>" maxlength="<? echo $pref_etiq_hspace_len; ?>"> mm <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_vspace_req ?> id="libelle"><? echo _T("Espacement vertical :"); ?></TH> 
								<td><input type="text" name="pref_etiq_vspace" value="<? echo $pref_etiq_vspace; ?>" maxlength="<? echo $pref_etiq_vspace_len; ?>"> mm <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_hsize_req ?> id="libelle"><? echo _T("Largeur étiquette :"); ?></TH> 
								<td><input type="text" name="pref_etiq_hsize" value="<? echo $pref_etiq_hsize; ?>" maxlength="<? echo $pref_etiq_hsize_len; ?>"> mm <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_vsize_req ?> id="libelle"><? echo _T("Hauteur étiquette :"); ?></TH> 
								<td><input type="text" name="pref_etiq_vsize" value="<? echo $pref_etiq_vsize; ?>" maxlength="<? echo $pref_etiq_vsize_len; ?>"> mm <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_cols_req ?> id="libelle"><? echo _T("Nombre de colonnes d'étiquettes :"); ?></TH> 
								<td><input type="text" name="pref_etiq_cols" value="<? echo $pref_etiq_cols; ?>" maxlength="<? echo $pref_etiq_cols_len; ?>"> <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_rows_req ?> id="libelle"><? echo _T("Nombre de lignes d'étiquettes :"); ?></TH> 
								<td><input type="text" name="pref_etiq_rows" value="<? echo $pref_etiq_rows; ?>" maxlength="<? echo $pref_etiq_rows_len; ?>"> <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_corps_req ?> id="libelle"><? echo _T("Corps du texte :"); ?></TH> 
								<td><input type="text" name="pref_etiq_corps" value="<? echo $pref_etiq_corps; ?>" maxlength="<? echo $pref_etiq_corps_len; ?>"> <SPAN class="exemple"><? echo _T("(Entier)"); ?></SPAN></td> 
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _T("Compte administrateur (indépendant des adhérents) :"); ?></TH>
							</TR> 
							<TR> 
								<TH <? echo $pref_admin_login_req ?> id="libelle"><? echo _T("Identifiant :"); ?></TH> 
								<TD><INPUT type="text" name="pref_admin_login" value="<? echo $pref_admin_login; ?>" maxlength="<? echo $pref_admin_login_len; ?>"></TD>
							</TR>
							<TR> 
								<TH <? echo $pref_admin_pass_req ?> id="libelle"><? echo _T("Mot de passe :"); ?></TH> 
								<TD><INPUT type="text" name="pref_admin_pass" value="<? echo $pref_admin_pass; ?>" maxlength="<? echo $pref_admin_pass_len; ?>"></TD>
							</TR>
							<TR> 
								<TH align="center" colspan="2"><BR><INPUT type="submit" name="valid" value="<? echo _T("Enregistrer"); ?>"></TH> 
						  </TR> 
							</TABLE> 
						</DIV>
						<BR> 
						<? echo _T("NB : Les champs obligatoires apparaissent en"); ?> <font style="color: #FF0000"><? echo _T("rouge"); ?></font>. 
						</BLOCKQUOTE> 
						</FORM> 
<? 
  include("footer.php") 
?>
