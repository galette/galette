<? 
 
/* ajouter_contribution.php
 * - Saisie d'une contributions
 * Copyright (c) 2003 Frédéric Jaqcuot
 * Copyright (c) 2004 Georges Khaznadar
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
	
// variables d'erreur (pour affichage)	    
$error_detected = "";

//
// DEBUT parametrage des champs
//  On recupere de la base la longueur et les flags des champs
//  et on initialise des valeurs par defaut

// recuperation de la liste de champs de la table
$fields = &$DB->MetaColumns(PREFIX_DB."cotisations");
while (list($champ, $proprietes) = each($fields)){
  $proprietes_arr = get_object_vars($proprietes);
  // on obtient name, max_length, type, not_null, has_default, primary_key,
  // auto_increment et binary		
  
  $fieldname = $proprietes_arr["name"];
  $fieldreq = $fieldname."_req";
  $fieldlen = $fieldname."_len";
  
  // on ne met jamais a jour id_cotis -> on le saute
  if ($fieldname!="id_cotis")
    $$fieldname = "";
  
  // definissons  aussi la longueur des input text
  $max_tmp = $proprietes_arr["max_length"];
  if ($max_tmp == "-1")
    $max_tmp = 10;
  $$fieldlen = $max_tmp;
  
  // et s'ils sont obligatoires (à partir de la base)
  if ($proprietes_arr["not_null"]==1){
    $$fieldreq = " style=\"color: #FF0000;\"";
  }
  else{
    $$fieldreq = "";
  }
}
reset($fields);
// il faut écrire le montant de la cotisation !
$montant_cotis_req=" style=\"color: #FF0000;\"";

// et les valeurs par defaut
$id_type_cotis = "1";
$duree_mois_cotis = "12";

//
// FIN parametrage des champs
// 

if (isset($_POST["login_adh"])) $login_adh=$_POST["login_adh"];
if (isset($_GET["login_adh"])) $login_adh=$_GET["login_adh"];
$requete = "SELECT nom_adh, prenom_adh, id_adh FROM ".PREFIX_DB."adherents 
               WHERE login_adh=".$DB->qstr($login_adh);
$resultat = $DB->Execute($requete);
if (!$resultat->EOF){
  $nom_adh = $resultat->fields[0];
  $prenom_adh = $resultat->fields[1];
  $id_adh=$resultat->fields[2];
  $resultat->Close();
}


//
// Validation du formulaire
//
  
if (isset($_POST["valid"]) && $_POST["self_contribution"])
{
  // verification de champs
  $update_string = "";
  $insert_string_fields = "";
  $insert_string_values = "";
  
  // recuperation de la liste de champs de la table
  //$fields = &$DB->MetaColumns(PREFIX_DB."cotisations");
  while (list($champ, $proprietes) = each($fields)){
    $proprietes_arr = get_object_vars($proprietes);
    // on obtient name, max_length, type, not_null, has_default, primary_key,
    // auto_increment et binary		
    
    $fieldname = $proprietes_arr["name"];
    $fieldreq = $fieldname."_req";
    
    // on ne met jamais a jour id_cotis -> on le saute
    if ($fieldname!="id_cotis"){			
      if (isset($_POST[$fieldname]))
	$post_value=trim($_POST[$fieldname]);
      else			
	$post_value="";
      
      // on declare les variables pour la présaisie en cas d'erreur
      $$fieldname = htmlentities(stripslashes($post_value),ENT_QUOTES);
      
      // vérification de la présence des champs obligatoires
      if ($$fieldreq!="" && $post_value=="")
	$error_detected = "<LI>"._("- Check that all mandatory fields are filled in.")."</LI>";
      else{
	$value = "";
	// validation des dates			
	if($proprietes_arr["type"]=="date"){
	  if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", 
		   $post_value, $array_jours)){
	    if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]))
	      $value=$DB->DBDate(mktime(0,0,0,$array_jours[2],$array_jours[1],$array_jours[3]));
	    else{
	      $error_detected .= "<LI>"._("- Non valid date!")."</LI>";
	    }
	  }
	  else{
	    $error_detected .= "<LI>"._("- Wrong date format (dd/mm/yyyy)!")."</LI>";
	  }
	}elseif(strstr($proprietes_arr["type"],"int")){
	  if (is_numeric($post_value) || $post_value=="")
	    $value=$DB->qstr($post_value,ENT_QUOTES);
	  else{
	    $error_detected .= "<LI>"._("- The duration must be an integer!")."</LI>";
	  }
	}elseif(strstr($proprietes_arr["type"],"float")){
	  $us_value = strtr($post_value, ",", ".");
	  if (is_numeric($us_value) || $us_value=="")
	    $value=$DB->qstr($us_value,ENT_QUOTES);
	  else{
	    $error_detected .= "<LI>"._("- The amount must be an integer!")."</LI>";
	  }
	}else{
	  // on se contente d'escaper le html et les caracteres speciaux
	  $value = $DB->qstr($post_value,ENT_QUOTES);
	}
	
	// mise à jour des chaines d'insertion/update
	$update_string .= ",".$fieldname."=".$value;
	$insert_string_fields .= ",".$fieldname;
	$insert_string_values .= ",".$value;		
      }
    }
  }
  reset($fields);
  
  // modif ou ajout
  if ($error_detected==""){  	
    // ajout
    
    $requete = "INSERT INTO ".PREFIX_DB."cotisations (" . 
      substr($insert_string_fields,1) . ") VALUES (" . 
      substr($insert_string_values,1) . ")";
    
    dblog(_("Add a self-contribution:").
	  " ".strtoupper($nom_adh)." ".$prenom_adh, $requete);							
    $DB->Execute($requete);
    
    // mise a jour de l'échéance
    $date_fin = get_echeance($DB, $id_adh);
    if ($date_fin!="")
      $date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
    else
      $date_fin_update = "'NULL'";
    
    $requete = "UPDATE ".PREFIX_DB."adherents SET date_echeance=".
      $date_fin_update." WHERE id_adh='".$id_adh."'";
    $DB->Execute($requete);
    
    // retour à la liste
    header("location: voir_adherent?id_adh=".$id_adh);
  }	
}

	
//	
// Pré-remplissage des champs
// la date de creation de fiche, ici vide car nouvelle fiche
if ($date_cotis==""){
  $date_cotis = date("d/m/Y");
}
include("header.php");

?> 

<H1 class="titre"><? echo _("Contribution card"); ?> (<? if ($id_cotis!="") echo _("modification"); else echo _("creation"); ?>)</H1>
<FORM action="self_contribution.php" method="post"> 
  <input type="hidden" name="self_contribution" value="1">					
  <input type="hidden" name="id_adh" value="<? echo $id_adh ?>">					
  <?
    // Affichage des erreurs
    if ($error_detected!=""){
  ?>
  <DIV id="errorbox">
    <H1><? echo _("- ERROR -"); ?></H1>
    <UL>
      <? echo $error_detected; ?>
    </UL>
  </DIV>
  <?
    }
  ?>						
  <BLOCKQUOTE>
    <div align="center">
      <table border="0" id="input-table"> 
        <tr> 
          <TH id="libelle"><? echo _("Contributor:"); ?></TH> 
	  <td>
	    <input type="hidden" name="nom_adh" value="<? echo $nom_adh;?>">
	    <? echo $nom_adh." ".$prenom_adh; ?>
	  </td> 
	  <TH id="libelle"><? echo _("Contribution type:"); ?></TH> 
	  <td>
	    <input type="hidden" name="id_type_cotis" value="7">
	    <!-- 7 is for "Cotisation annuelle (à payer)" -->
	    <? echo _("Annual fee (to be paid)"); ?>
	  </td> 
	</tr>
        <tr>
	  <TH id="libelle" <? echo $montant_cotis_req ?>><? echo _("Amount:"); ?></TH> 
	  <td><input type="text" name="montant_cotis" value="<? echo $montant_cotis; ?>" maxlength="<? echo $montant_cotis_len; ?>"></td> 
	  <TH id="libelle" <? echo $duree_mois_cotis_req ?>><? echo _("Extension of adhesion:"); ?></TH> 
	  <td><input type="text" name="duree_months_cotis" value="<? echo $duree_months_cotis; ?>" maxlength="<? echo $duree_months_cotis_len; ?>"> <? echo _("months"); ?></td>
	</tr>
        <tr> 
	  <TH id="libelle" <? echo $date_cotis_req ?>><? echo _("Date of contribution:"); ?><br>&nbsp;</TH> 
	  <td colspan="3"><input type="text" name="date_cotis" value="<? echo $date_cotis; ?>" maxlength="10"><BR><DIV class="exemple"><? echo _("(dd/mm/yyyy format)"); ?></DIV></td> 
        </tr> 
        <tr> 
	  <TH id="libelle" <? echo $info_cotis_req ?>><? echo _("Comments:"); ?></TH> 
	  <td colspan="3"><textarea name="info_cotis" cols="61" rows="6"><? echo $info_cotis; ?></textarea></td> 
        </tr> 
        <tr> 
	  <TH align="center" colspan="4"><BR><input type="submit" name="valid" value="<? echo _("Save"); ?>"></TH> 
        </tr> 
      </table> 
    </div>
    <br> 
    <? echo _("NB : The mandatory fields are in"); ?> <font style="color: #FF0000"><? echo _("red"); ?></font>. 
  </BLOCKQUOTE> 
  <input type="hidden" name="id_cotis" value="<? echo $id_cotis ?>"> 
</form> 
</body></html>
