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
        include_once("includes/i18n.inc.php"); 
	include(WEB_ROOT."includes/lang.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 

	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");
	
	// On vérifie si on a une référence => modif ou création

	// variables d'erreur (pour affichage)	    
 	$error_detected = "";
 	$warning_detected = "";

	  //
	 // DEBUT parametrage des champs
	//  on initialise des valeurs par defaut

	// recup des donnees
        $requete = "SELECT nom_pref
                    FROM ".PREFIX_DB."preferences";
        $result = &$DB->Execute($requete);
        while (!$result->EOF)
        {
		$fieldname = $result->fields["nom_pref"];
                $$fieldname = "";
	
		// declaration des champs obligatoires
		$fieldreq = $fieldname."_req";
		if ($fieldname=="pref_nom" ||
		    $fieldname=="pref_lang" ||
		    $fieldname=="pref_numrows" ||
		    $fieldname=="pref_log" ||
		    $fieldname=="pref_email_nom" ||
		    $fieldname=="pref_email" ||
		    $fieldname=="pref_etiq_marges" ||
		    $fieldname=="pref_etiq_hspace" ||
		    $fieldname=="pref_etiq_vspace" ||
		    $fieldname=="pref_etiq_hsize" ||
		    $fieldname=="pref_etiq_vsize" ||
		    $fieldname=="pref_etiq_cols" ||
		    $fieldname=="pref_etiq_rows" ||
		    $fieldname=="pref_etiq_corps" ||
		    $fieldname=="pref_admin_login" ||
		    $fieldname=="pref_admin_pass")
			$$fieldreq = " style=\"color: #FF0000;\"";
		else
			$$fieldreq = "";

		 $result->MoveNext();
        }
        $result->Close();

	  //
	 // FIN parametrage des champs
	// 	    	    
    
	  //
	 // Validation du formulaire
	//
  
	if (isset($_POST["valid"]))
	{
  		// verification de champs
	  	$insert_values = array();

		// recuperation de la liste de champs de la table
		$requete = "SELECT nom_pref
			    FROM ".PREFIX_DB."preferences";
		$result=&$DB->Execute($requete);
		while (!$result->EOF)
		{
			$fieldname = $result->fields["nom_pref"];
			$fieldreq = $fieldname."_req";

			if (isset($_POST[$fieldname]))
				$post_value=trim($_POST[$fieldname]);
			else			
				$post_value="";
					
			// on declare les variables pour la présaisie en cas d'erreur
			$$fieldname=htmlentities(stripslashes($post_value),ENT_QUOTES);

			// vérification de la présence des champs obligatoires
			$req = $$fieldreq;
			if ($req!="" && $post_value=="")
				$error_detected .= "<LI>"._("- Mandatory field empty.")."</LI>";
			else
			{
				// validation de la langue
				if ($fieldname=="pref_lang")
 				{
 					if (file_exists(WEB_ROOT . "lang/lang_" . $post_value . ".php"))
		 				$value = $DB->qstr($post_value, true);
		 			else
				  		$error_detected .= "<LI>"._("- Non-valid language!")."</LI>";
				}
				// validation des adresses mail				
				elseif ($fieldname=="pref_email")
 				{
 					$post_value=strtolower($post_value);
					if (!is_valid_email($post_value) && $post_value!="")
				  	$error_detected .= "<LI>"._("- Non-valid E-Mail address!")."</LI>";
					else
		 				$value = $DB->qstr($post_value, true);
				}
				// validation login
  				elseif ($fieldname=="pref_admin_login")
 				{
 					if (strlen($post_value)<4)
 						$error_detected .= "<LI>"._("- The username must be composed of at least 4 characters!")."</LI>";
 					else
 					{
 						// on vérifie que le login n'est pas déjà utilisé
 						$requete = "SELECT id_adh
 							    FROM ".PREFIX_DB."adherents
 							    WHERE login_adh=". $DB->qstr($post_value, true);
 						if ($id_adh!="")
 							$requete .= " AND id_adh!=" . $DB->qstr($id_adh, true);

 						$result2 = &$DB->Execute($requete);
						if (!$result2->EOF)
	 						$error_detected .= "<LI>"._("- This username is already used by another member !")."</LI>";
						else
	 						$value = $DB->qstr($post_value, true);
					}
 				}
				// validation des entiers
				elseif ($fieldname=="pref_numrows" ||
				        $fieldname=="pref_etiq_marges" ||
		                        $fieldname=="pref_etiq_hspace" ||
					$fieldname=="pref_etiq_vspace" ||
					$fieldname=="pref_etiq_hsize" ||
					$fieldname=="pref_etiq_vsize" ||
					$fieldname=="pref_etiq_cols" ||
					$fieldname=="pref_etiq_rows" ||
					$fieldname=="pref_etiq_corps")
 				{
 					// évitons la divison par zero
 					if ($fieldname=="pref_numrows" && $post_value=="0")
 						$post_value="1";
 					
 					if ((is_numeric($post_value) && $post_value >=0) || $post_value=="")
						$value=$DB->qstr($post_value,ENT_QUOTES);
					else
						$error_detected .= "<LI>"._("- The numbers and measures have to be integers!")."</LI>";
 				}
				// validation mot de passe
 				elseif ($fieldname=="pref_admin_pass")
 				{
 					if (strlen($post_value)<4)
 						$error_detected .= "<LI>"._("- The password must be of at least 4 characters!")."</LI>";
 					else
 						$value = $DB->qstr($post_value, true);
 				}
 				else
 				{
 					// on se contente d'escaper le html et les caracteres speciaux
					$value = $DB->qstr($post_value, true);
				}

				// mise a jour des chaines d'insertion
				if ($value=="''")
					$value="NULL";
				$insert_values[$fieldname] = $value;	
			}
			$result->MoveNext();
		}
		$result->Close();
  
  		// modif ou ajout
  		if ($error_detected=="")
  		{  
			// vidage des preferences
			$requete = "DELETE FROM ".PREFIX_DB."preferences";
			$DB->Execute($requete);
			
			// insertion des nouvelles preferences
			while (list($champ,$valeur)=each($insert_values))
			{
				$requete = "INSERT INTO ".PREFIX_DB."preferences 
					    (nom_pref, val_pref)
					    VALUES (".$DB->qstr($champ).",".$valeur.");";
				$DB->Execute($requete);
			}
			
			// ajout photo
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
					@unlink(WEB_ROOT . "photos/logo.jpg");
					@unlink(WEB_ROOT . "photos/logo.gif");
					@unlink(WEB_ROOT . "photos/logo.jpg");
					@unlink(WEB_ROOT . "photos/tn_logo.jpg");
					@unlink(WEB_ROOT . "photos/tn_logo.gif");
					@unlink(WEB_ROOT . "photos/tn_logo.jpg");
						
					// copie fichier temporaire				 		
					if (!@move_uploaded_file($tmp_name,WEB_ROOT . "photos/logo".$ext_image))
						$warning_detected .= "<LI>"._("- The photo seems not to be transferred correctly. But registration has been made.")."</LI>";
				 	else
                    {
						resizeimage(WEB_ROOT . "photos/logo".$ext_image,WEB_ROOT . "photos/tn_logo".$ext_image,130,130);
                    }  
			 	}
			 	else
				{
					if (function_exists("ImageCreateFromGif"))
			 			$warning_detected .= "<LI>"._("- The transfered file isn't a valid image (GIF, PNG or JPEG). But registration has been made.")."</LI>"; 
					else
			 			$warning_detected .= "<LI>"._("- The transfered file isn't a valid image (PNG or JPEG). But registration has been made.")."</LI>"; 
				}
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
 		@unlink(WEB_ROOT . "photos/tn_logo.jpg");
 		@unlink(WEB_ROOT . "photos/tn_logo.png");
 		@unlink(WEB_ROOT . "photos/tn_logo.gif");
 	} 	
	
	  //	
	 // Pré-remplissage des champs
	//  avec des valeurs issues de la base
	//  -> donc uniquement si l'enregistrement existe et que le formulaire
	//     n'a pas déja été posté avec des erreurs (pour pouvoir corriger)
	
	if (!isset($_POST["valid"]) || (isset($_POST["valid"]) && $error_detected==""))
	{
		// recup des donnees
		$requete = "SELECT * 
		  	    FROM ".PREFIX_DB."preferences";
		$result = &$DB->Execute($requete);
        	if ($result->EOF)
	                header("location: index.php");
		else
		{
			while (!$result->EOF)
			{
				$fieldname=$result->fields["nom_pref"];
				$$fieldname = htmlentities(stripslashes(addslashes($result->fields["val_pref"])), ENT_QUOTES);
				$result->MoveNext();
			}
		}
		$result->Close();
	}
	else
	{
		// initialisation des champs
	}

	include("header.php");

?> 
						<H1 class="titre"><? echo _("Settings"); ?></H1>
						<FORM action="preferences.php" method="post" enctype="multipart/form-data"> 
<?
	// Affichage des erreurs
	if ($error_detected!="")
	{
?>
  	<DIV id="errorbox">
  		<H1><? echo _("- ERROR -"); ?></H1>
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
  		<H1><? echo _("- WARNING -"); ?></H1>
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
								<TH colspan="2" id="header"><? echo _("General information:"); ?></TH>
							</TR> 
							<TR> 
								<TH <? echo $pref_nom_req ?> id="libelle"><? echo _("Name (corporate name) of the association:"); ?></TH> 
								<TD><INPUT type="text" name="pref_nom" value="<? echo $pref_nom; ?>" maxlength="190"></TD>
							</TR>
							<TR>
								<TH id="libelle"><? echo _("Logo:"); ?></TH> 
								<td>
								<?
									$logo_asso = "";
									if (file_exists(WEB_ROOT . "photos/tn_logo.jpg"))
										$logo_asso = "photos/tn_logo.jpg";
									elseif (file_exists(WEB_ROOT . "photos/tn_logo.gif"))
										$logo_asso = "photos/tn_logo.gif";
									elseif (file_exists(WEB_ROOT . "photos/tn_logo.png"))
										$logo_asso = "photos/tn_logo.png";
									
									if ($logo_asso != "")
									{
										if (function_exists("ImageCreateFromString"))
											$imagedata = getimagesize($logo_asso);
										else
											$imagedata = array("130","");
								?>
									<img src="photo.php?tn=1&id_adh=logo&?nocache=<? echo time(); ?>" border="1" alt="<? echo _("Picture"); ?>" width="<? echo $imagedata[0]; ?>" height="<? echo $imagedata[1]; ?>"><BR>
									<input type="submit" name="del_photo" value="<? echo _("Delete the picture"); ?>">
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
								<TH<? echo $pref_adresse_req ?> id="libelle"><? echo _("Address:"); ?></TH> 
								<td><input type="text" name="pref_adresse" value="<? echo $pref_adresse; ?>" maxlength="190" size="42"></td> 
							</TR>						   
						  <TR>
								<TH id="libelle">&nbsp;</TH> 
								<td><input type="text" name="pref_adresse2" value="<? echo $pref_adresse2; ?>" maxlength="190" size="42"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_cp_req ?> id="libelle"><? echo _("Zip Code:"); ?></TH> 
								<td><input type="text" name="pref_cp" value="<? echo $pref_cp; ?>" maxlength="10"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_ville_req ?> id="libelle"><? echo _("City:"); ?></TH> 
								<td><input type="text" name="pref_ville" value="<? echo $pref_ville; ?>" maxlength="100"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_pays_req ?> id="libelle"><? echo _("Country:"); ?></TH> 
								<td><input type="text" name="pref_pays" value="<? echo $pref_pays; ?>" maxlength="50"></td> 
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _("Galette's parameters:"); ?></TH>
							</TR> 
						  <TR>
								<TH<? echo $pref_lang_req ?> id="libelle"><? echo _("Language:"); ?></TH>
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
										<OPTION value="<? echo $file; ?>" <? isSelected($file,$pref_lang) ?>><? echo ucfirst(_($file)); ?></OPTION>
<?
		}
	}
	closedir($dir_handle);
?>
									</SELECT>
								</TD> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_numrows_req ?> id="libelle"><? echo _("Lines / Page:"); ?></TH> 
								<td><input type="text" name="pref_numrows" value="<? echo $pref_numrows; ?>" maxlength="5"> <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_log_req ?> id="libelle"><? echo _("Logging level:"); ?></TH> 
								<TD>
									<SELECT name="pref_log">
										<OPTION value="0" <? isSelected("0",$pref_log) ?>><? echo _("Disabled"); ?></OPTION>
										<OPTION value="1" <? isSelected("1",$pref_log) ?>><? echo _("Normal"); ?></OPTION>
										<OPTION value="2" <? isSelected("2",$pref_log) ?>><? echo _("Detailed"); ?></OPTION>
									</SELECT>
								</TD>
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _("Mail settings:"); ?></TH>
							</TR> 
						  <TR>
								<TH<? echo $pref_email_nom_req ?> id="libelle"><? echo _("Sender name:"); ?></TH> 
								<td><input type="text" name="pref_email_nom" value="<? echo $pref_email_nom; ?>" maxlength="50"></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_email_req ?> id="libelle"><? echo _("Sender Email:"); ?></TH> 
								<td><input type="text" name="pref_email" value="<? echo $pref_email; ?>" maxlength="100" size="30"></td> 
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _("Label generation parameters:"); ?></TH>
							</TR> 
						  <TR>
								<TH<? echo $pref_etiq_marges_req ?> id="libelle"><? echo _("Margins:"); ?></TH> 
								<td><input type="text" name="pref_etiq_marges" value="<? echo $pref_etiq_marges; ?>" maxlength="4"> mm <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_hspace_req ?> id="libelle"><? echo _("Horizontal spacing:"); ?></TH> 
								<td><input type="text" name="pref_etiq_hspace" value="<? echo $pref_etiq_hspace; ?>" maxlength="4"> mm <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_vspace_req ?> id="libelle"><? echo _("Vertical spacing:"); ?></TH> 
								<td><input type="text" name="pref_etiq_vspace" value="<? echo $pref_etiq_vspace; ?>" maxlength="4"> mm <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_hsize_req ?> id="libelle"><? echo _("Label width:"); ?></TH> 
								<td><input type="text" name="pref_etiq_hsize" value="<? echo $pref_etiq_hsize; ?>" maxlength="4"> mm <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_vsize_req ?> id="libelle"><? echo _("Label height:"); ?></TH> 
								<td><input type="text" name="pref_etiq_vsize" value="<? echo $pref_etiq_vsize; ?>" maxlength="4"> mm <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_cols_req ?> id="libelle"><? echo _("Number of label columns:"); ?></TH> 
								<td><input type="text" name="pref_etiq_cols" value="<? echo $pref_etiq_cols; ?>" maxlength="4"> <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_rows_req ?> id="libelle"><? echo _("Number of label lines:"); ?></TH> 
								<td><input type="text" name="pref_etiq_rows" value="<? echo $pref_etiq_rows; ?>" maxlength="4"> <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
						  <TR>
								<TH<? echo $pref_etiq_corps_req ?> id="libelle"><? echo _("Font size:"); ?></TH> 
								<td><input type="text" name="pref_etiq_corps" value="<? echo $pref_etiq_corps; ?>" maxlength="4"> <SPAN class="exemple"><? echo _("(Integer)"); ?></SPAN></td> 
							</TR>						   
							<TR>
								<TH colspan="2" id="header"><BR><? echo _("Admin account (independant of members):"); ?></TH>
							</TR> 
							<TR> 
								<TH <? echo $pref_admin_login_req ?> id="libelle"><? echo _("Username:"); ?></TH> 
								<TD><INPUT type="text" name="pref_admin_login" value="<? echo $pref_admin_login; ?>" maxlength="20"></TD>
							</TR>
							<TR> 
								<TH <? echo $pref_admin_pass_req ?> id="libelle"><? echo _("Password:"); ?></TH> 
								<TD><INPUT type="text" name="pref_admin_pass" value="<? echo $pref_admin_pass; ?>" maxlength="20"></TD>
							</TR>
							<TR> 
								<TH align="center" colspan="2"><BR><INPUT type="submit" name="valid" value="<? echo _("Save"); ?>"></TH> 
						  </TR> 
							</TABLE> 
						</DIV>
						<BR> 
						<? echo _("NB : The mandatory fields are in"); ?> <font style="color: #FF0000"><? echo _("red"); ?></font>. 
						</BLOCKQUOTE> 
						</FORM> 
<? 
  include("footer.php") 
?>
