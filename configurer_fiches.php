<? 
/* configurer_fiches.php
 * - Configuration des fiches adhérents
 * Copyright (c) 2004 Laurent Pelecq <laurent.pelecq@soleil.org>
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
    include(WEB_ROOT."includes/categories.inc.php");

    if ($_SESSION["logged_status"]==0)
            header("location: index.php");
    if ($_SESSION["admin_status"]==0) 
            header("location: voir_adherent.php");

    $error_detected = "";
    
    if (isset($_POST["valid"]))
    {
        if ($_POST["perm_cat"] != $category_separator &&
            (!isset($_POST["name_cat"]) || $_POST["name_cat"] == "")) {
            $error_detected .= "<LI>"._("- Le champ nom ne doit pas être vide.")."</LI>";
        } else {
            $name_cat = $_POST["name_cat"];
            $perm_cat = $_POST["perm_cat"];
            $type_cat = $_POST["type_cat"];
            $size_cat = $_POST["size_cat"];
	    $requete = "SELECT COUNT(*) + 1 AS idx 
			FROM $info_cat_table";
	    $result = $DB->Execute($requete);
            $requete = "INSERT INTO $info_cat_table
                        ( index_cat, name_cat, perm_cat, type_cat, size_cat )
                        VALUES ('".$result->fields["idx"]."',".$DB->qstr($name_cat, true).", $perm_cat, $type_cat, $size_cat)";
            $DB->Execute($requete);
        }
    } else {
        $action = "";
        foreach (array("sup", "up", "down") as $varname) {
            if (isset($_GET[$varname]) && is_numeric($_GET[$varname])) {
                $action = $varname;
                $id_cat = (integer)$_GET[$varname];
                break;
            }
        }
        $DB->StartTrans();
        $res = $DB->Execute("SELECT index_cat FROM $info_cat_table WHERE id_cat=$id_cat");
        if (!$res->EOF) {
            $old_rank = $res->fields[0];
            if ($action == "sup") {
                $DB->Execute("UPDATE $info_cat_table SET index_cat=index_cat-1 WHERE index_cat > $old_rank");
                $DB->Execute("DELETE FROM $info_adh_table WHERE id_cat=$id_cat");
                $DB->Execute("DELETE FROM $info_cat_table WHERE id_cat=$id_cat");
            } elseif ($action != "") {
                $direction = $action == "up" ? -1: 1;
                $new_rank = $old_rank + $direction;
                $DB->Execute("UPDATE $info_cat_table SET index_cat=$old_rank WHERE index_cat=$new_rank");
                $DB->Execute("UPDATE $info_cat_table SET index_cat=$new_rank WHERE id_cat=$id_cat");
            }
        }
        $DB->CompleteTrans();
    }
            
    include("header.php");

    // Affichage des erreurs
    if ($error_detected!="")
    {
?>
    <DIV id="errorbox">
        <H1><? echo _("- ERREUR -"); ?></H1>
        <UL>
            <? echo $error_detected; ?>
        </UL>
    </DIV>
<?
    }
?> 
    <H1 class="titre"><? echo _("Configuration des fiches"); ?></H1>
    <FORM action="configurer_fiches.php" method="post" enctype="multipart/form-data">
        <TABLE width="100%" id="input-table"> 
            <TR>
                <TH class="listing">#</TH> 
                <TH class="listing left"><? echo _("Nom"); ?></TH>
                <TH class="listing"><? echo _("Visibilité"); ?></TH>
                <TH class="listing"><? echo _("Type"); ?></TH>
                <TH class="listing"><? echo _("Nombre"); ?></TH>
                <TH class="listing"><? echo _("Actions"); ?></TH>
            </TR>
<?
    $count = 1;
    $confirm_sup = str_replace("\n", "\\n",
                               addslashes(_("Voulez-vous vraiment supprimer cette catégorie de la base, ceci supprimera aussi toutes les données associées ?")));
    $request = "SELECT id_cat, index_cat, name_cat, perm_cat, type_cat, size_cat FROM $info_cat_table ORDER BY index_cat";
    $result = $DB->Execute($request);
    while (!$result->EOF)
    {
        $id = $result->fields[0];
        $index = $result->fields[1];
        $name = $result->fields[2];
        switch($result->fields[3]) {
            case $perm_all: $perm = _('tous'); break;
            case $perm_admin: $perm = _('admin'); break;
            default: $perm = _('inconnu');
        }
        switch($result->fields[4]) {
            case $category_separator: $type_name = _('séparateur'); break;
            case $category_text: $type_name = _('texte libre'); break;
            case $category_field: $type_name = _('champ'); break;
            default: $type_name = _('inconnu');
        }
        $size = $result->fields[5];
?>        
            <TR>
                <TD class="listing"><? echo $index; ?></TD> 
                <TD class="listing left"><? echo $name; ?></TD>
                <TD class="listing left"><? echo $perm; ?></TD>
                <TD class="listing left"><? echo $type_name; ?></TD>
                <TD class="listing"><? echo $size; ?></TD>
                <TD class="listing center">
                <A onClick="return confirm('<? echo $confirm_sup; ?>')" href="configurer_fiches.php?sup=<? echo $id ?>">
                    <IMG src="images/icon-trash.png" alt="<? echo _("[sup]"); ?>" border="0" width="11" height="13">
                </A>
<?
        if ($index == 1) {
?>                
                    <IMG src="images/icon-empty.png" alt="" border="0" width="11" height="13">
<?
        } else {
?>                
                    <A href="configurer_fiches.php?up=<? echo $id; ?>">
                        <IMG src="images/icon-up.png" alt="<? echo _("[haut]"); ?>" border="0" width="9" height="8">
                    </A>
<?
        }
?>                
<?
        if ($index == $result->RecordCount()) {
?>                
                    <IMG src="images/icon-empty.png" alt="" border="0" width="11" height="13">
<?
        } else {
?>                
                    <A href="configurer_fiches.php?down=<? echo $id; ?>">
                        <IMG src="images/icon-down.png" alt="<? echo _("[bas]"); ?>" border="0" width="9" height="8">
                    </A>
<?
        }
?>                
                </TD>
            </TR>
<?    
        $result->MoveNext();
        ++$count;
    }
    $result->Close();

?>        
            <TR>
                <TD width="15" class="listing">&nbsp;</TD> 
                <TD class="listing left">
                    <INPUT size="40" type="text" name="name_cat">
                </TD>
                <TD width="60" class="listing left">
                    <SELECT name="perm_cat">
                        <OPTION value="<?php echo $perm_all; ?>"><? echo _("tous"); ?></OPTION>
                        <OPTION value="<?php echo $perm_admin; ?>"><? echo _("admin"); ?></OPTION>
                    </SELECT>
                </TD>
                <TD width="60" class="listing left">
                    <SELECT name="type_cat">
                        <OPTION value="<?php echo $category_separator; ?>"><? echo _("séparateur"); ?></OPTION>
                        <OPTION value="<?php echo $category_text; ?>"><? echo _("texte libre"); ?></OPTION>
                        <OPTION value="<?php echo $category_field; ?>"><? echo _("champ"); ?></OPTION>
                    </SELECT>
                </TD>
                <TD class="listing">
                    <INPUT size="2" maxlength="2" type="text" value="1" name="size_cat">
                </TD>
		<TD class="listing center"><INPUT type="submit" name="valid" value="<? echo _("Ajouter"); ?>"></TD>
            </TR>
        </TABLE> 
    </FORM> 

<? 
  include("footer.php") 
?>
