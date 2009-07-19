<?php 

// Copyright © 2009 Manuel Menal
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
 * Configuration des fiches
 *
 * @package Galette
 * 
 * @author     Manuel Menal <mmenal@hurdfr.org>
 * @copyright  2009 Manuel Menal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Rev$
 * @since      
 */

require_once('includes/galette.inc.php');

if( !$login->isLogged() )
{
	header("location: index.php");
	die();
}
if( !$login->isAdmin() )
{
	header("location: voir_adherent.php");
	die();
}

$error_detected = array();

$id_fields = array('statuts' => 'id_statut',
		   'types_cotisation' => 'id_type_cotis');
$name_fields = array('statuts' => 'libelle_statut',
		     'types_cotisation' => 'libelle_type_cotis');

function my_db_query ($query)
{
  global $error_detected, $mdb;

  $res = $mdb->query($query);
  if ($mdb->inError())
    {
      $error_detected[] = _T("- Database error: ").$mdb->getErrorMessage();
      return null;
    }
  return $res;
}

function my_db_execute ($query)
{
  global $error_detected, $mdb;

  $res = $mdb->execute($query);
  if ($mdb->inError())
    {
      $error_detected[] = _T("- Database error: ").$mdb->getErrorMessage();
      return null;
    }
  return $res;
}

function del_entry ($id, $table)
{
  global $error_detected, $id_fields, $name_fields, $mdb, $DB;

  if (!is_numeric($id))
    {
      $error_detected[] = _T("- ID must be an integer!");
      return;
    }
  
  // Check if it exists.
  $query = "SELECT ".$name_fields[$table]." FROM ".PREFIX_DB."$table
                    WHERE ".$id_fields[$table]." = $id";
  $res = my_db_query($query);
  if (!$res || $res->numRows() == 0)
    {
      if ($res)
	$error_detected[] = _T("- Label does not exist");
      return;
    }
  $label = $res->fetchOne();

  // Check if it's used.
  if ($table == "statuts")
    $query = "SELECT * FROM ".PREFIX_DB."adherents
                      WHERE id_statut = $id";
  elseif ($table == "types_cotisation")
    $query = "SELECT * FROM ".PREFIX_DB."cotisations
                      WHERE id_type_cotis = $id";
  
  $res = my_db_query($query);
  if (!$res || $res->numRows() > 0)
    {
      if ($res)
	$error_detected[] = _T("- Cannot delete this label: it's still used");
      return;
    }
  
  // Delete.
  $query = "DELETE FROM ".PREFIX_DB."$table
                    WHERE ".$id_fields[$table]." = $id";
  if (!my_db_execute($query))
    return;

  delete_dynamic_translation($DB, $label, $error_detected);

  return;
}

// Validate an input. Return a correct (quoted) value.
function check_field_value ($table, $key, $value, $op)
{
  global $error_detected, $mdb;
  
  if ($table == 'statuts')
    {
      switch ($key)
	{
	case 'priorite_statut':
	  if (!is_numeric($value))
	    $error_detected[] = _T("- Priority must be an integer!");
	  break;
	  
	case 'libelle_statut':
	  // Avoid duplicates.
	  if ($op == 'add')
	    {
	      $query = "SELECT id_statut
                          FROM ".PREFIX_DB."statuts
                          WHERE libelle_statut=".$mdb->quote($mdb->escape($value));
	      $result = my_db_query($query);
	      if ($result && ($result->numRows() > 0))
		$error_detected[] = _T("- This label is already used!");
	    }
	  $value = $mdb->quote($mdb->escape($value));
	  break;
	}
    }
  elseif ($table == 'types_cotisation')
    {
      switch ($key)
	{
	case 'libelle_type_cotis':
	  // Avoid duplicates.
	  if ($op == 'add')
	    {
	      $query = "SELECT id_type_cotis
                          FROM ".PREFIX_DB."types_cotisation
                          WHERE libelle_type_cotis=".$mdb->quote($mdb->escape($value));
	      $result = my_db_query($query);
	      if ($result && ($result->numRows() > 0))
		$error_detected[] = _T("- This label is already used!");
	    }
          $value = $mdb->quote($mdb->escape($value));
	  break;
	  
	case 'cotis_extension':
	  if (!is_numeric($value) || (($value != 0) && ($value != 1)))
	    $error_detected[] = _T("- 'Extends membership?' field must be either true or false! (current value:").$value.")";
	  break;
	}
    }
  
  // Return correct (escapes, quoted...) value.
  return $value;
}

function modify_entry ($id, $table)
{
  global $error_detected, $id_fields, $name_fields, $mdb, $DB;
  $label = '';

  if (!is_numeric($id))
    {
      $error_detected[] = _T("- ID must be an integer!");
      return;
    }

  // Check if it exists.
  $query = "SELECT ".$name_fields[$table]." FROM ".PREFIX_DB."$table
                    WHERE ".$id_fields[$table]." = $id";

  $res = my_db_query($query);
  if (!$res || ($res->numRows() == 0))
    {
      if ($res)
	$error_detected[] = _T("- Label does not exist");
      return;
    }
  $oldlabel = $res->fetchOne();

  // Check input and build query.
  $update_string = '';
  $fields = $mdb->getDb()->tableInfo(PREFIX_DB.$table);
  while (list($fieldid, $field) = each($fields))
    {
      $key = strtolower($field['name']);

      if (!isset($_POST[$key]))
	{
	  // cotis_extension is a checkbox. If unchecked, it won't appear
	  // at all in POST.
	  if ($key == 'cotis_extension')
	    $value = 0;
	  else
	    continue;
	}
      else
	$value = $_POST[$key];

      // Get unquoted value for dynamic translation.
      if ($key == $name_fields[$table])
	$label = $value;

      $value = check_field_value ($table, $key, $value, 'update');

      // $value gets quoted by check_field_value.
      $update_string .= ", ".$key."=".$value;
    }

  if (count($error_detected))
    return;

  // Modify.

  $query = "UPDATE ".PREFIX_DB."$table
                    SET ".substr($update_string, 1)."
                    WHERE ".$id_fields[$table]."='$id'";
  if (!my_db_execute($query))
    return;

  if ($oldlabel != $label)
    {
      delete_dynamic_translation($DB, $oldlabel, $error_detected);
      add_dynamic_translation($DB, $label, $error_detected);
    }
}

function add_entry ($table)
{
  global $error_detected, $id_fields, $name_fields, $DB, $mdb;
  $insert_string_fields = '';
  $insert_string_values = '';
  $label = '';

  // Check input and build query.
  $fields = $mdb->getDb()->tableInfo(PREFIX_DB.$table);
  while (list($keyid, $key) = each($fields))
    {
      $key = strtolower($key['name']);
      $value = '';
      
      // Skip ID, it's automatically computed.
      if ($key == $id_fields[$table])
	continue;
      
      if (isset($_POST[$key]))
	$value = trim($_POST[$key]);
      // Check missing fields.
      if ($value == '')
	$error_detected[] =_T("- Mandatory field empty.")." ($key)";
      
      // Get unquoted value, it gets quoted by add_dynamic_translation().
      if ($key == $name_fields[$table])
	$label = $value;
      
      $value = check_field_value ($table, $key, $value, 'add');
      
      $insert_string_fields .= ", ".$key;
      $insert_string_values .= ", ".$value;
    }

  if (count($error_detected))
    return;

  // Get the next free id.
  // XXX: it's not atomic because the id is not autoincremented. Either
  // use a mutex or change the schema.
  $query = '';
  {
    $idn = $id_fields[$table];
    $ttable = PREFIX_DB.$table;
    $query = "SELECT MIN($idn+1) FROM $ttable AS t1
                      WHERE NOT EXISTS(SELECT $idn FROM $ttable AS t2
                       WHERE t2.$idn = t1.$idn + 1)";
  }

  $res = my_db_query($query);
  if (!$res)
    return;

  $idx = $res->fetchOne();
  
  $insert_string_fields .= ", ".$id_fields[$table];
  $insert_string_values .= ", ".$idx;

  // Insert entry.
  $query = "INSERT INTO ".PREFIX_DB."$table
                    (".substr($insert_string_fields, 1).")
                    VALUES (".substr($insert_string_values, 1).")";

  if (!my_db_execute($query))
    {
      print substr($insert_string_values, 1).": ".$DB->ErrorMsg();
      return;
    }

  // User should be able to translate the new labels dynamically.
  add_dynamic_translation ($DB, $label, $error_detected);

  return;
}
	
function edit_entry ($id, $table)
{
  global $id_fields, $name_fields, $tpl, $error_detected;

  $query = "SELECT * FROM ".PREFIX_DB."$table
                    WHERE ".$id_fields[$table]." = $id";
  $result = my_db_query($query);
  if (!$result || $result->numRows() == 0)
    {
      if ($res)
	$error_detected[] = _T("- Label does not exist");
      return;
    }

  // Fill $entry and pass it to the template.
  $entry = $result->fetchRow(MDB2_FETCHMODE_ASSOC);
  foreach ($entry as $field => $data) 
    {
      if (is_string ($entry[$field]))
	$entry[$field] = htmlentities($data);
      // Display name in the user locale.
      if ($field == $name_fields[$table])
	$entry[$field] = _T($data);
    }

  $tpl->assign ('entry', $entry);
  if ($table == 'statuts')
    $tpl->assign ('form_title', _T("Edit status"));
  elseif ($table == 'types_cotisation')
    $tpl->assign ('form_title', _T("Edit contribution type"));
}

function list_entries ($table)
{
  global $error_detected, $id_fields, $name_fields, $tpl;
  
  $query = "SELECT * FROM ".PREFIX_DB."$table ORDER BY ".$id_fields[$table];
  $result = my_db_query($query);
  if (!$result)
    return;

  $entries = array();
  $count = 0;

  while ($row = $result->fetchRow())
    {
      $entries[$count]['id'] = $row->$id_fields[$table];
      // Display name in the user locale. New labels will be added to
      // dynamic translations with the user locale.
      $entries[$count]['name'] = _T($row->$name_fields[$table]);
      
      if ($table == 'types_cotisation')
	$entries[$count]['extends'] = ($row->cotis_extension ? _T("Yes") : _T("No"));
      
      elseif ($table == 'statuts')
	$entries[$count]['priority'] = $row->priorite_statut;
      
      ++$count;
    }
  
  $tpl->assign('namef', $name_fields[$table]);
  $tpl->assign('entries', $entries);
  if ($table == 'statuts')
    $tpl->assign('form_title', _T("User statuses"));
  elseif ($table == 'types_cotisation')
    $tpl->assign('form_title', _T("Contribution types"));
}

// MAIN CODE.
// Choose which labels (status, contributions...) first.
if (!isset($_REQUEST['table']))
{
  $all_forms = array('types_cotisation' => _T("Contribution types"),
		     'statuts' => _T("User statuses"));
  $tpl->assign("all_forms", $all_forms);
}
else
{
  // 'statuts', 'types_cotisation'...
  $table = $_REQUEST['table'];
  $tpl->assign('table', $table);
  
  // Display a specific form to edit a label.
  // Otherwise, display a list of entries.
  if (isset($_GET['id']))
    edit_entry(trim($_GET['id']), $table);
  else
    {
      if (isset($_GET['del']))
	del_entry(trim($_GET['del']), $table);
      elseif (isset($_POST['new']))
	add_entry($table);
      elseif (isset($_POST['mod']))
	modify_entry(trim($_POST['mod']), $table);
      // Show the list.
      list_entries($table);
    }
}


$tpl->assign("error_detected", $error_detected);

if (isset($_GET['id']))
     $content = $tpl->fetch("editer_intitule.tpl");
     else
     $content = $tpl->fetch("editer_intitules.tpl");
$tpl->assign("content",$content);

$tpl->display("page.tpl");

?>
