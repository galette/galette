<? 
/* self_adherent.php
 * - Saisie d'un adhérent par lui-même
 * Copyright (c) 2004 Frédéric Jaqcuot, Georges Khaznadar
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
include(WEB_ROOT."includes/session.inc.php"); 
include(WEB_ROOT."includes/categories.inc.php");

// DEBUT parametrage des champs
//  On recupere de la base la longueur et les flags des champs
//   et on initialise des valeurs par defaut

// recuperation de la liste de champs de la table
$fields = &$DB->MetaColumns(PREFIX_DB."adherents");
while (list($champ, $proprietes) = each($fields)){
  $proprietes_arr = get_object_vars($proprietes);
  // on obtient name, max_length, type, not_null, has_default, primary_key,
  // auto_increment et binary		
  
  $fieldname = $proprietes_arr["name"];
  
  // on ne met jamais a jour id_adh
  if ($fieldname!="id_adh" && $fieldname!="date_echeance")
    $$fieldname= "";
  
  $fieldlen = $fieldname."_len";
  $fieldreq = $fieldname."_req";
  
  // definissons  aussi la longueur des input text
  $max_tmp = $proprietes_arr["max_length"];
  if ($max_tmp == "-1")
    $max_tmp = 10;
  $fieldlen = $fieldname."_len";
  $$fieldlen=$max_tmp;
  
  // par défaut les champs ne sont pas obligatoires ici
  $$fieldreq = "";
}
//voici la liste des champs obligatoires :
$nom_adh_req = "style=\"color: #FF0000;\"";
$prenom_adh_req = "style=\"color: #FF0000;\"";
$adresse_adh_req = "style=\"color: #FF0000;\"";
$cp_adh_req = "style=\"color: #FF0000;\"";
$ville_adh_req = "style=\"color: #FF0000;\"";
$email_adh_req = "style=\"color: #FF0000;\"";
$login_adh_req = "style=\"color: #FF0000;\"";
$mdp_adh_req = "style=\"color: #FF0000;\"";

reset($fields);

//
// FIN parametrage des champs
// 	    	    

if (!isset($_POST["valid"]) || 
    !PasswordCheck($_POST["mdp_adh"],$_POST["mdp_crypt"])){
  if (!isset($_POST["titre_adh"])){
    $titre_adh="1"; //monsieur par défaut
  }
  if (isset($_POST["valid"])){
    $pref_lang=$_POST["pref_lang"];
    include(WEB_ROOT."includes/lang.inc.php"); 
    $warning_detected=_T("Erreur en recopiant le mot de passe : ").$_POST["mdp_adh"];
    //	
    // Pré-remplissage des champs
    foreach($_POST as $k => $v){
      $$k=$v;
    }
  }
} else {
  // initialisation des champs
  $pref_lang=$_POST["pref_lang"];
  include(WEB_ROOT."includes/lang.inc.php"); 
  $listkey="";
  $listvalues="";
  foreach($_POST as $k => $v){
    if ($k!="valid" && 
	$k!="id_adh" &&
	$k!="mdp_crypt"){
      $listkeys .= ",".$k." ";
      $listvalues .=",'".addslashes($v)."' ";
    }
  }
  $date_crea_adh = date("Y-m-d");
  $listkeys .= ",date_crea_adh";
  $listvalues .=",'".$date_crea_adh."'";
  $listkeys = substr($listkeys,1);
  $listvalues = substr($listvalues,1);
  $req="INSERT INTO ".PREFIX_DB."adherents
         (".$listkeys.")
         VALUES (".$listvalues.")";
  dblog(_T("Auto-inscription d'adhérent :")." ".strtoupper($_POST["nom_adh"])." ".$_POST["prenom_adh"], $req);
  $DB->Execute($req);
  // il est temps d'envoyer un mail
  $mail_subject = _T("Vos identifiants Galette");
  $mail_text =  _T("Bonjour,")."\n";
  $mail_text .= "\n";
  $mail_text .= _T("Vous venez d'être inscrit sur le système de gestion d'adhérents de l'association.")."\n";
  $mail_text .= _T("Il vous est désormais possible de suivre en temps réel l'état de votre adhésion")."\n";
  $mail_text .= _T("et de mettre à jour vos coordonnées par l'interface web prévue à cet effet.")."\n";
  $mail_text .= "\n";
  $mail_text .= _T("Veuillez vous identifier à cette adresse :")."\n";
  $mail_text .= "http://".$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"])."\n";
  $mail_text .= "\n";
  $mail_text .= _T("Identifiant :")." ".custom_html_entity_decode($login_adh)."\n";
  $mail_text .= _T("Mot de passe :")." ".custom_html_entity_decode($mdp_adh)."\n";
  $mail_text .= "\n";
  $mail_text .= _T("A trés bientôt !")."\n";
  $mail_text .= "\n";
  $mail_text .= _T("(ce mail est un envoi automatique)")."\n";
  $mail_headers = "From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">\nContent-Type: text/plain; charset=iso-8859-15\n";
  mail ($email_adh,$mail_subject,$mail_text, $mail_headers);
  header("location: index.php");
  echo "<html><header></header><body></body></html>";
  exit();
}


$pref_lang=PREF_LANG;
// force la langue d'administration par défaut
include(WEB_ROOT."includes/lang.inc.php"); 
include("header.php");

?> 
 
<H1 class="titre">
  <? echo _T("Fiche adhérent"); ?> 
  (<? if ($id_adh!="") echo _T("modification"); else echo _T("création"); ?>)
</H1>
<SCRIPT LANGUAGE="JavaScript1.1">
function isblank(s){
  //from the Javascript book, D. Flanagan, ed. O'Reilly 1998
  for(var i=0; i< s.length;i++){
    var c=s.charAt(i);
    if ((c!=' ') && (c!='\n') && (c!='\t')) return false;
  }
  return true;
}

function verify(f){
  //loop through the form elements and verify that mandatory
  //informations don't miss
  empty_fields="";
  for (var i=0; i<f.length;i++){
    e = f.elements[i];
    if ((e.type=="text" || e.type=="textarea") && e.required){
      if (e.value==null || e.value=="" || isblank(e.value)){
        empty_fields +="\n     - "+e.name;
      }
    }
  }
  if (!empty_fields){
    return true;
  }
  msg ="-------------------------------------------------------\n";
  msg+="<? echo _T("Le formulaire n'est pas valide car certains champs"); ?>"+"\n";
  msg+="<? echo _T("obligatoires sont vides, veuillez les remplir :"); ?>"+"\n";
  msg+="-------------------------------------------------------\n";
  if (empty_fields){
    msg+=empty_fields;
  }
  alert(msg);
  return false;
}
</SCRIPT>
<FORM action="self_adherent.php" method="post" 
  enctype="multipart/form-data" 
  onSubmit="
    this.nom_adh.required=true;
    this.prenom_adh.required=true;
    this.adresse_adh.required=true;
    this.cp_adh.required=true;
    this.ville_adh.required=true;
    this.email_adh.required=true;
    this.login_adh.required=true;
    this.mdp_adh.required=true;
    return verify(this);">
  <? if ($error_detected!="") { ?>
  <DIV id="errorbox">
    <H1><? echo _T("- ERREUR -"); ?></H1>
    <UL>
      <? echo $error_detected; ?>
    </UL>
  </DIV>
  <? }
    if ($warning_detected!="") {
  ?>
  <DIV id="warningbox">
    <H1><? echo _T("- AVERTISSEMENT -"); ?></H1>
    <UL>
      <? echo $warning_detected; ?>
    </UL>
  </DIV>
  <? } ?>	
  <input type="hidden" name="id_statut" value="4">
  <input type="hidden" name="titre_adh" value="1">
  <input type="hidden" name="activite_adh" value="1">
					
  <BLOCKQUOTE>
    <DIV align="center">
      <TABLE border="0" id="input-table"> 
        <TR> 
	  <TH <? echo $titre_adh_req ?> id="libelle">
            <? echo _T("Titre :"); ?>
          </TH> 
	  <TD colspan="3">
	    <INPUT type="radio" name="titre_adh" value="3" <? isChecked($titre_adh,"3") ?>> 
            <? echo _T("Mademoiselle"); ?>&nbsp;&nbsp;
	    <INPUT type="radio" name="titre_adh" value="2" <? isChecked($titre_adh,"2") ?>> 
            <? echo _T("Madame"); ?>&nbsp;&nbsp;
	    <INPUT type="radio" name="titre_adh" value="1" <? isChecked($titre_adh,"1") ?>> 
            <? echo _T("Monsieur"); ?>&nbsp;&nbsp;
	  </TD> 
        </TR> 
        <TR> 
  	  <TH <? echo $nom_adh_req ?> id="libelle">
            <? echo _T("Nom :"); ?>
          </TH> 
  	  <TD>
            <INPUT type="text" name="nom_adh" value="<? echo $nom_adh; ?>" maxlength="<? echo $nom_adh_len; ?>" >
          </TD> 
  	  <TD colspan="2" rowspan="4" align="center" width="130">
            <? echo _T("Vous pouvez préparer une photo à télécharger après avoir envoyé"); ?>
            <? echo _T("votre cotisation.") ?>
            <!-- l'adhérent qui s'auto-inscrit ne peut pas tout de suite expédier une image -->
  	  </TD>
        </TR>
        <TR>
  	  <TH <? echo $prenom_adh_req ?> id="libelle">
            <? echo _T("Prénom :"); ?>
          </TH> 
  	  <TD>
            <INPUT type="text" name="prenom_adh" value="<? echo $prenom_adh; ?>" maxlength="<? echo $prenom_adh_len; ?>" >
          </TD> 
        </TR>						   
        <TR> 
  	  <TH <? echo $pseudo_adh_req ?> id="libelle">
            <? echo _T("Pseudo :"); ?>
          </TH> 
  	  <TD>
            <INPUT type="text" name="pseudo_adh" value="<? echo $pseudo_adh; ?>" maxlength="<? echo $pseudo_adh_len; ?>">
          </TD> 
        </TR> 
        <TR> 
  	  <TH <? echo $ddn_adh_req ?> id="libelle">
            <? echo _T("Date de naissance :"); ?><br>&nbsp;
          </TH> 
  	  <TD>
            <INPUT type="text" name="ddn_adh" value="<? echo $ddn_adh; ?>" maxlength="10">
            <BR>
            <DIV class="exemple">
              <? echo _T("(format jj/mm/aaaa)"); ?>
            </DIV>
          </TD>
        </TR>
        <TR>
  	  <TH <? echo $prof_adh_req ?> id="libelle">
  	    <? echo _T("Profession :"); ?>
  	  </TH> 
  	  <TD>
  	    <input type="text" name="prof_adh" value="<? echo $prof_adh; ?>" maxlength="<? echo $prof_adh_len; ?>">
  	  </TD> 
  	  <TH id="libelle">
  	    &nbsp;<!-- ? echo _T("Photo :"); ? -->
  	  </TH> 
  	  <TD> 
  	    &nbsp;<!-- pas de téléchargement de photo encore -->
  	  </TD> 
        </TR> 
        <TR>
  	  <TH id="libelle">
  	    <? echo _T("Je souhaite apparaître dans la liste des membres :"); ?>
  	  </TH>
  	  <TD>
  	    <input type="checkbox" name="bool_display_info" value="1"<? isChecked($bool_display_info,"1") ?>>
  	  </TD> 
  	  <TH id="libelle">
  	    <? echo _T("Langue :") ?>
  	  </TH>
  	  <TD>
  	    <SELECT NAME="pref_lang">
  	      <?
  	        $path = "lang";
                $dir_handle = @opendir($path);
                while ($file = readdir($dir_handle)) {
  		  if (substr($file,0,5)=="lang_" && substr($file,-4)==".php") {
  		    $file = substr(substr($file,5),0,-4);
  	      ?>
  	      <OPTION value="<? echo $file; ?>" <? isSelected($pref_lang,$file) ?> style="padding-left: 30px; background-image: url(lang/<? echo $file.".gif"; ?>); background-repeat: no-repeat">
  		<? echo ucfirst(_T($file)); ?>
  	      </OPTION>
  	      <?
  		  }
  	        }
                closedir($dir_handle);
              ?>
            </SELECT>
          </TD>
        </TR>
        <TR> 
          <TH colspan="4" id="header">&nbsp;</TH> 
        </TR>
        <TR> 
  	  <TH id="libelle" <? echo $adresse_adh_req ?>>
  	    <? echo _T("Adresse :"); ?>
  	  </TH> 
  	  <TD colspan="3">
  	    <INPUT type="text" name="adresse_adh" value="<? echo $adresse_adh; ?>" maxlength="<? echo $adresse_adh_len; ?>" size="63">
  	    <BR>
  	    <INPUT type="text" name="adresse2_adh" value="<? echo $adresse2_adh; ?>" maxlength="<? echo $adresse2_adh_len; ?>" size="63">
  	  </TD> 
        </TR> 
        <TR> 
  	  <TH id="libelle" <? echo $cp_adh_req ?>>
  	    <? echo _T("Code Postal :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="cp_adh" value="<? echo $cp_adh; ?>" maxlength="<? echo $cp_adh_len; ?>">
  	  </TD> 
  	  <TH id="libelle" <? echo $ville_adh_req ?>>
  	    <? echo _T("Ville :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="ville_adh" value="<? echo $ville_adh; ?>" maxlength="<? echo $ville_adh_len; ?>">
  	  </TD> 
        </TR> 
        <TR> 
  	  <TH id="libelle" <? echo $pays_adh_req ?>>
  	    <? echo _T("Pays :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="pays_adh" value="<? echo $pays_adh; ?>" maxlength="<? echo $pays_adh_len; ?>">
  	  </TD> 
  	  <TH id="libelle" <? echo $tel_adh_req ?>>
  	    <? echo _T("Tel :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="tel_adh" value="<? echo $tel_adh; ?>" maxlength="<? echo $tel_adh_len; ?>">
  	  </TD> 
        </TR> 
        <TR> 
  	  <TH id="libelle" <? echo $gsm_adh_req ?>>
  	    <? echo _T("GSM :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="gsm_adh" value="<? echo $gsm_adh; ?>" maxlength="<? echo $gsm_adh_len; ?>">
  	  </TD> 
  	  <TH id="libelle" <? echo $email_adh_req ?>>
  	    <? echo _T("E-Mail :"); ?>
  	  </TH>
  	  <TD>
  	    <INPUT type="text" name="email_adh" value="<? echo $email_adh; ?>" maxlength="<? echo $email_adh_len; ?>" size="30">
  	  </TD> 
        </TR> 
        <TR> 
  	  <TH id="libelle" <? echo $url_adh_req ?>>
  	    <? echo _T("Site Web :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="url_adh" value="http://" maxlength="<? echo $url_adh_len; ?>" size="30">
  	  </TD> 
  	  <TH id="libelle" <? echo $icq_adh_req ?>>
  	    <? echo _T("ICQ :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="icq_adh" value="<? echo $icq_adh; ?>" maxlength="<? echo $icq_adh_len; ?>">
  	  </TD> 
        </TR> 
        <TR> 
  	  <TH id="libelle" <? echo $jabber_adh_req ?>>
  	    <? echo _T("Jabber :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="jabber_adh" value="<? echo $jabber_adh; ?>" maxlength="<? echo $jabber_adh_len; ?>" size="30">
  	  </TD> 
  	  <TH id="libelle" <? echo $msn_adh_req ?>>
  	    <? echo _T("MSN :"); ?>
  	  </TH> 
  	  <TD>
  	    <INPUT type="text" name="msn_adh" value="<? echo $msn_adh; ?>" maxlength="<? echo $msn_adh_len; ?>" size="30">
  	  </TD> 
        </TR> 
        <TR> 
  	  <TH colspan="4" id="header">&nbsp;</TH> 
        </TR>
        <TR> 
  	  <TH id="libelle" <? echo $login_adh_req ?>>
  	    <? echo _T("Identifiant :"); ?><BR>&nbsp;
          </TH> 
  	  <TD>
  	    <INPUT type="text" name="login_adh" value="<? echo $login_adh; ?>" maxlength="<? echo $login_adh_len; ?>"><BR>
  	    <DIV class="exemple">
  	      <? echo _T("(au moins 4 caractères)"); ?>
  	    </DIV>
  	  </TD> 
  	  <TH id="libelle" <? echo $mdp_adh_req ?>>
  	    <? echo _T("Mot de passe :"); ?><BR>&nbsp;
          </TH> 
  	  <TD>
            <?
              $c=PasswordImage();
              $f=PasswordImagePath($c);
              echo _T("Recopiez dans le cadre le mot de passe présenté dans l'image.")."<BR>\n";
            ?>
            <INPUT type="hidden" name="mdp_crypt" value="<? echo $c ?>">
            <IMG SRC="<? echo $f ?>"><BR>
  	    <INPUT type="text" name="mdp_adh" value="" maxlength="<? echo $mdp_adh_len; ?>">
  	  </TD> 
        </TR>
        <TR> 
          <TH id="libelle" <? echo $info_public_adh_req ?>>
            <? echo _T("Autres informations :"); ?>
          </TH> 
  	  <TD colspan="3">
  	    <TEXTAREA name="info_public_adh" cols="61" rows="6">
  	      <? echo $info_public_adh; ?>
  	    </TEXTAREA>
  	  </TD> 
        </TR> 
        <?
  	  $requete = "SELECT id_cat, index_cat, name_cat, perm_cat, type_cat, size_cat FROM $info_cat_table";
          $requete .= " WHERE perm_cat=$perm_all";
          $requete .= " ORDER BY index_cat";
  	  $res_cat = $DB->Execute($requete);
  	  while (!$res_cat->EOF) {
	    $id_cat = $res_cat->fields[0];
	    $rank_cat = $res_cat->fields[1];
	    $name_cat = $res_cat->fields[2];
	    $perm_cat = $res_cat->fields[3];
	    $type_cat = $res_cat->fields[4];
	    $size_cat = $res_cat->fields[5];
      
	    if ($type_cat == $category_separator) {
              for ($i = 0; $i < $size_cat; ++$i) {
        ?>                                                
        <TR>
  	  <TH colspan="4" id="header">&nbsp;</TH>
        </TR> 
        <?
  	  }
        } else {
  	  $cond = "id_cat=$id_cat";
  	  if (is_numeric($id_adh))
  	    $cond .= " and id_adh=$id_adh";
  	  else
  	    $cond .= " and 1=2";
  	  // Cette condition est stupide
  	  // Je l'ai rajoutee pour eviter d'avoir des valeurs a la creation de nouvelles fiches
  	  // TODO : recoder proprement
  	  
  	  $res_info = $DB->Execute("SELECT val_info, index_info FROM ".PREFIX_DB."adh_info WHERE $cond ORDER BY index_info");
  	  $current_size = $size_cat;
  	  if ($size_cat == 0)
  	    $current_size = $res_info->RecordCount() + 1;
  	  for ($i = 0; $i < $current_size; ++$i) {
        ?> 
        <TR>
  	  <? if ($i == 0) { ?> 
          <TH id="libelle" rowspan="<?php echo $current_size; ?>" <?php echo $info_public_adh_req ?> >
            <INPUT type="hidden" name="info_field_size_<?php echo $id_cat; ?>" value="<?php echo $current_size; ?>" >
  	    <?php echo $name_cat."&nbsp;:"; ?> 
  	  </TH> 
  	  <? }
             $field_name = "info_field_".$id_cat."_".$i;
  	     $val = $res_info->EOF ? "" : $res_info->fields[0];
  	  ?> 
  	  <TD colspan="3">
            <? if ($type_cat == $category_text) { ?> 
  	    <TEXTAREA name="<?php echo $field_name; ?>" cols="61" rows="6">
              <?php echo $val; ?>
  	    </TEXTAREA>
  	    <? } elseif ($type_cat == $category_field) { ?> 
            <INPUT type="text" name="<?php echo $field_name; ?>" value="<? echo $val; ?>" size="63">
  	    <? } ?> 
  	  </TD> 
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
          <TH align="center" colspan="4"><BR>
            <INPUT type="submit" name="valid" value="<? echo _T("Enregistrer"); ?>">
          </TH> 
        </TR> 
      </TABLE> 
    </DIV>
    <BR> 
    <? echo _T("NB : Les champs obligatoires apparaissent en"); ?> 
    <FONT style="color: #FF0000"><? echo _T("rouge"); ?></FONT>. 
  </BLOCKQUOTE> 
  <INPUT type="hidden" name="id_adh" value="<? echo $id_adh ?>"> 
</FORM> 
<? 
  include("footer.php") 
?>
