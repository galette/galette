<? 
/* categories.inc.php
 * - Categories configuration
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
 
    $category_separator = 0;
    $category_text = 1;
    $category_field = 2;
    
    $perm_all = 0;
    $perm_admin = 1;

    $info_cat_table = PREFIX_DB."info_categories";
    $info_adh_table = PREFIX_DB."adh_info";
    
    function set_adh_info($DB, $id_adh, $id_cat, $index_info, $value) {
        global $info_adh_table;
        $result = false;
        $DB->StartTrans();
        $request = "SELECT COUNT(*) FROM $info_adh_table WHERE id_adh=$id_adh AND id_cat=$id_cat AND index_info=$index_info";
        $res_count = $DB->Execute($request);
        if ($res_count != false && !$res_count->EOF) {
            $count = (int)($res_count->fields[0]);
            if ($value == "")
                $request = "DELETE FROM $info_adh_table WHERE id_cat=$id_cat AND id_adh=$id_adh AND index_info=$index_info";
            else {
                $value = $DB->qstr($value, true);
                if ($count > 0)
                    $request = "UPDATE $info_adh_table SET val_info=$value WHERE id_cat=$id_cat AND id_adh=$id_adh AND index_info=$index_info";
                else
                    $request = "INSERT INTO $info_adh_table (id_adh, id_cat, index_info, val_info) VALUES ($id_adh, $id_cat, $index_info, $value)";
            }
            $result = ($DB->Execute($request) != false);
        }
        $DB->CompleteTrans();
        return $result;
    }

?>
