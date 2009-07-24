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
require_once('classes/status.class.php');
require_once('classes/contributions_types.class.php');

if (!$login->isLogged())
{
  header("location: index.php");
  die();
}
if (!$login->isAdmin())
{
  header("location: voir_adherent.php");
  die();
}

$error_detected = array();

$fields = array('Status' => array('id' => 'id_statut',
				  'name' => 'libelle_statut',
				  'field' => 'priorite_statut'),
		'ContributionsTypes' => array('id' => 'id_type_cotis',
					    'name' => 'libelle_type_cotis',
					    'field' => 'cotis_extension'));
$forms = array('ContributionsTypes' => _T("Contribution types"),
	       'Status' => _T("User statuses"));

function del_entry ($id, $class)
{
  global $error_detected, $DB;

  if (!is_numeric($id))
    {
      $error_detected[] = _T("- ID must be an integer!");
      return;
    }

  /* Check if it exists. */
  $label = $class->getLabel($id);
  if (!$label || MDB2::isError($label))
    {
      if ($label) $error_detected[] = _T("- Database error: ").$class->getErrorMessage();
      else $error_detected[] = _T("- Label does not exist");
      return;
    }

  /* Check if it's used. */
  $ret = $class->isUsed($id);
  if ($ret != 0)
    {
      if ($ret == -1) { $error_detected[] = _T("- Database error: ").$class->getErrorMessage(); }
      elseif ($ret == 1) { $error_detected[] = _T("- Cannot delete this label: it's still used"); }
      return;
    }

  /* Delete. */
  $ret = $class->delete($id);

  if ($ret != 0)
    {
      if ($ret == -2) $error_detected[] = _T("- Label does not exist");
      elseif ($ret == -1) { $error_detected[] = _T("- Database error: ").$class->getErrorMessage(); }
      return;
    }

  delete_dynamic_translation($DB, $label, $error_detected);

  return;
}

// Validate an input. Returns true or false.
function check_field_value ($class, $key, $value)
{
  global $fields, $error_detected;

  switch ($key)
    {
    case ($fields[get_class($class)]['name']):
      if (!is_string($value))
	{
	  $error_detected[] =_T("- Mandatory field empty.")." ($key)";
	  return false;
	}
      break;

    case ($fields[get_class($class)]['field']):
      if (get_class($class) == 'Status')
	if (!is_numeric($value)) 
	  {
	    $error_detected[] = _T("- Priority must be an integer!");
	    return false;
	  }
      elseif (get_class($class) == 'ContributionsTypes')
	// Value must be either 0 or 1.
	if (!is_numeric($value) || (($value != 0) && ($value != 1)))
	  {
	    $error_detected[] = _T("- 'Extends membership?' field must be either 0 or 1! (current value:").$value.")";
	    return false;
	  }
      break;
    }

  return true;
}

function modify_entry ($id, $class)
{
  global $error_detected, $fields,  $DB;

  if (!is_numeric($id))
    {
      $error_detected[] = _T("- ID must be an integer!");
      return;
    }

  $label = '';
  $oldlabel = $class->getLabel($id);
  if (!$oldlabel || MDB2::isError($oldlabel))
    {
      if ($oldlabel) $error_detected[] = _T("- Database error: ").$class->getErrorMessage();
      else $error_detected[] = _T("- Label does not exist");
      return;
    }

  $toup = array();
  /* Check field values. */
  foreach ($fields[get_class($class)] as $field)
    {
      $value = null;
      if (!isset($_POST[$field]))
	{
	  if ($field == $fields['ContributionsTypes']['field'])
	    $value = 0;
	  else
	    continue;
	}
      else $value = $_POST[$field];

      if ($field == $fields[get_class($class)]['name'])
	$label = $value;

      check_field_value($class, $field, $value);

      $toup[$field] = trim($value);
    }

  /* Update only if all fields are OK. */
  if (count($error_detected))
    return;

  foreach ($toup as $field => $value)
    {
      $ret = $class->update($id, $field, $value);
      if ($ret != 0)
	{
	  if ($ret == -2) $error_detected[] = _T("- Label does not exist");
	  elseif ($ret == -1) { $error_detected[] = _T("- Database error: ").$class->getErrorMessage(); }
	}
    }


  if (isset($label) && ($oldlabel != $label))
    {
      delete_dynamic_translation($DB, $oldlabel, $error_detected);
      add_dynamic_translation($DB, $label, $error_detected);
    }

  return;
}


function add_entry ($class)
{
  global $error_detected, $fields, $DB;

  $label = trim($_POST[$fields[get_class($class)]['name']]);
  $field = trim($_POST[$fields[get_class($class)]['field']]);

  check_field_value($class, $fields[get_class($class)]['name'],
		    $label);
  check_field_value($class, $fields[get_class($class)]['field'],
		    $field);

  if (count($error_detected))
    return;

  $ret = $class->add($label, $field);
  if ($ret < 0)
    {
      if ($ret == -1) { $error_detected[] = _T("- Database error: ").$class->getErrorMessage(); }
      if ($ret == -2) { $error_detected[] = _T("- This label is already used!"); }
      return;
    }

  // User should be able to translate the new labels dynamically.
  add_dynamic_translation ($DB, $label, $error_detected);

  return;
}

function edit_entry ($id, $class)
{
  global $fields, $tpl, $error_detected;

  if (!is_numeric($id))
    {
      $error_detected[] = _T("- ID must be an integer!");
      return;
    }
  $entry = $class->get($id);

  if (!$entry || MDB2::isError($entry))
    {
      if ($entry) $error_detected[] = _T("- Database error: ").$class->getErrorMessage();
      else $error_detected[] = _T("- Label does not exist");
      return;
    }

  $entry->$fields[get_class($class)]['name'] = 
    htmlentities($entry->$fields[get_class($class)]['name'], 
		 ENT_QUOTES, 'UTF-8');
  
  $tpl->assign ('entry', get_object_vars($entry));
  if (get_class($class) == 'Status')
    $tpl->assign ('form_title', _T("Edit status"));
  elseif (get_class($class) == 'ContributionsTypes')
    $tpl->assign ('form_title', _T("Edit contribution type"));
}

function list_entries ($class)
{
  global $fields, $tpl;

  $list = $class->getList();

  $entries = array();
  foreach ($list as $key=>$row)
    {
      $entry['id'] = $key;
      $entry['name'] = $row[0];
      
      if (get_class($class) == 'ContributionsTypes')
	$entry['extends'] = ($row[1] ? _T("Yes") : _T("No"));
      elseif (get_class($class) == 'Status')
	$entry['priority'] = $row[1];
      
      $entries[] = $entry;
    }

  $tpl->assign('entries', $entries);

  if (get_class($class) == 'Status')
    $tpl->assign('form_title', _T("User statuses"));
  elseif (get_class($class) == 'ContributionsTypes')
    $tpl->assign('form_title', _T("Contribution types"));
}

// MAIN CODE.

function main()
{
  global $tpl;
  $class = null;

  # Show statuses list by default, instead of an empty table.
  if (!isset($_REQUEST['class']))
    $class = 'Status';
  else
    $class = $_REQUEST['class'];

  $tpl->assign('class', $class);

  if ($class == 'Status')
    $class = new Status;
  elseif ($class == 'ContributionsTypes')
    $class = new ContributionsTypes;

  // Display a specific form to edit a label.
  // Otherwise, display a list of entries.
  if (isset($_GET['id']))
    edit_entry(trim($_GET['id']), $class);
  else
    {
      if (isset($_GET['del']))
	del_entry(trim($_GET['del']), $class);
      elseif (isset($_POST['new']))
	add_entry($class);
      elseif (isset($_POST['mod']))
	modify_entry(trim($_POST['mod']), $class);
      // Show the list.
      list_entries($class);
    }
}

main();

/* Set template parameters and print. */

$tpl->assign("fields", $fields);
if (isset($_GET['id']))
  $content = $tpl->fetch("editer_intitule.tpl");
else
{
  $tpl->assign("all_forms", $forms);
  $tpl->assign("error_detected", $error_detected);
  $content = $tpl->fetch("editer_intitules.tpl");
}
$tpl->assign("content", $content);

$tpl->display("page.tpl");

?>
