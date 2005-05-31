<? 

/* picture.php
 * - Display a picture
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
	include(WEB_ROOT."includes/smarty.inc.php");
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0)
		$id_adh = $_SESSION["logged_id_adh"];
	else
		$id_adh = $_GET['id_adh'];

	function show_default_picture()
	{
		global $tpl;
		header('Content-type: image/png');
		readfile($tpl->template_dir.'/images/default.png');
	}

	if (!is_numeric($id_adh))
		show_default_picture();
	else
	{
		if (file_exists(WEB_ROOT.'photos/'.$id_adh.'.jpg'))
			readfile (WEB_ROOT.'photos/'.$id_adh.'.jpg');
		elseif (file_exists(WEB_ROOT.'photos/'.$id_adh.'.png'))
			readfile (WEB_ROOT.'photos/'.$id_adh.'.png');
		elseif (file_exists(WEB_ROOT.'photos/'.$id_adh.'.gif'))
			readfile (WEB_ROOT.'photos/'.$id_adh.'.gif');
		else
		{
			$sql = "SELECT picture,format FROM ".PREFIX_DB."pictures
				WHERE id_adh=".$id_adh;
			$result = &$DB->Execute($sql);
			if ($result->RecordCount()!=0)
			{
				$ext = '';
				switch($result->fields['format'])
				{
					case 'jpg':
						header('Content-type: image/jpeg');
						$ext = 'jpg';
						break;
					case 'png':
						header('Content-type: image/png');
						$ext = 'png';
						break;
					case 'gif':
						header('Content-type: image/gif');
						$ext = 'gif';
						break;
				}
				// We regenerate a physical picture file 
				$f = fopen(WEB_ROOT.'photos/'.$id_adh.'.'.$ext,"wb");
				fwrite ($f, $result->fields['picture']);
				fclose($f);
				echo $result->fields['picture'];
			}
			else
				show_default_picture();
		}
	}
													
?>
