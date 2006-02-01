<?
	require_once('includes/config.inc.php');
	require_once('includes/database.inc.php');
	require_once('includes/smarty.inc.php');

	class picture
	{
		var $HEIGHT;
		var $WIDTH;
		var $OPTIMAL_HEIGHT;
		var $OPTIMAL_WIDTH;
		var $FILE_PATH;
		var $FORMAT;
		var $MIME;
		var $HAS_PICTURE;
		
		// Constructor
		
		function picture($id_adh='')
		{
			// we first check if picture exists on filesystem
			$found_picture = '';

			// '!==' needed, otherwise ''==0
			if ($id_adh!=='')
			{
				if (file_exists(dirname(__FILE__).'/../photos/'.$id_adh.'.jpg'))
				{
					$found_picture = dirname(__FILE__).'/../photos/'.$id_adh.'.jpg';
					$format = 'jpg';
					$mime = 'image/jpeg';
				}
				elseif (file_exists(dirname(__FILE__).'/../photos/'.$id_adh.'.png'))
				{
					$found_picture = dirname(__FILE__).'/../photos/'.$id_adh.'.png';
					$format = 'png';
					$mime = 'image/png';
				}
				elseif (file_exists(dirname(__FILE__).'/../photos/'.$id_adh.'.gif'))
				{
					$found_picture = dirname(__FILE__).'/../photos/'.$id_adh.'.png';
					$format = 'gif';
					$mime = 'image/gif';
				}

				// if not, check in the database
				if ($found_picture=='')
				{
					global $DB;
					$sql = "SELECT picture,format
						FROM ".PREFIX_DB."pictures
						WHERE id_adh=".$id_adh;
					$result = &$DB->Execute($sql);
					if ($result->RecordCount()!=0)
					{
						// we must regenerate the picture file
						$f = fopen(dirname(__FILE__).'/../photos/'.$id_adh.'.'.$result->fields['format'],"wb");
						fwrite ($f, $result->fields['picture']);
						fclose($f);
						$found_picture = dirname(__FILE__).'/../photos/'.$id_adh.'.'.$result->fields['format'];
					}
				}
			}
			
			// if we still have no picture, take the default one
			if ($found_picture=='')
			{
				global $tpl;
				$found_picture = $tpl->template_dir.'images/default.png';
				$format = 'png';
				$mime = 'image/gif';
				$this->HAS_PICTURE = false;
			}
			else
				$this->HAS_PICTURE = true;

			$this->FILE_PATH = $found_picture;
			$this->FORMAT = $format;
			$this->MIME = $mime;

			list($width, $height) = getimagesize($found_picture);
			$this->HEIGHT = $height;
			$this->WIDTH = $width;
			$this->OPTIMAL_HEIGHT = $height;
			$this->OPTIMAL_WIDTH = $width;

			if ($this->HEIGHT > $this->WIDTH)
			{
				if ($this->HEIGHT > 200)
				{
					$ratio = 200 / $this->HEIGHT;
					$this->OPTIMAL_HEIGHT = 200;
					$this->OPTIMAL_WIDTH = $this->WIDTH * $ratio;
				}
			}
			else
			{
				if ($this->WIDTH > 200)
				{
					$ratio = 200 / $this->WIDTH;
					$this->OPTIMAL_WIDTH = 200;
					$this->OPTIMAL_HEIGHT = $this->HEIGHT * $ratio;
				}
			}
		}

		// Getters

		function getOptimalHeight()
		{
			return $this->OPTIMAL_HEIGHT;
		}
		
		function getOptimalWidth()
		{
			return $this->OPTIMAL_WIDTH;
		}
		
		function hasPicture()
		{
			return $this->HAS_PICTURE;
		}

		// Methods

		function display()
		{
			header('Content-type: '.$this->MIME);
			readfile($this->FILE_PATH);
		}

		// Helpers

		function delete($id)
		{
			global $DB;
			$sql = "DELETE FROM ".PREFIX_DB."pictures
				WHERE id_adh='".$id."'";
			if ( ! $DB->Execute($sql) )
				return false;
			else
			{
				if (file_exists(dirname(__FILE__).'/../photos/'.$id.'.jpg'))
					return unlink(dirname(__FILE__).'/../photos/'.$id.'.jpg');
				elseif (file_exists(dirname(__FILE__).'/../photos/'.$id.'.png'))
					return unlink(dirname(__FILE__).'/../photos/'.$id.'.png');
				elseif (file_exists(dirname(__FILE__).'/../photos/'.$id.'.gif'))
					return unlink(dirname(__FILE__).'/../photos/'.$id.'.gif');
			}
			return false;
		}

		function store($id, $tmpfile, $name)
		{
			// TODO : error codes
			// TODO : check file size
			// TODO : resize picture (if gd available)
			
			global $DB;
			
			$allowed_extensions = array('jpg', 'png', 'gif');
			$format_ok = false;
			foreach($allowed_extensions as $allowed_extension)
			{
				if (strtolower(substr($name,-4))=='.'.$allowed_extension)
				{
					$format_ok = true;
					$extension = $allowed_extension;
				}
			}
			if (!$format_ok)
				return false;

			$sql = "DELETE FROM ".PREFIX_DB."pictures
				WHERE id_adh='".$id."'";
			$DB->Execute($sql);
				
			move_uploaded_file($tmpfile, dirname(__FILE__).'/../photos/'.$id.'.'.$extension);
			$f = fopen(dirname(__FILE__).'/../photos/'.$id.'.'.$extension,'r');
			$picture = '';
			while ($r=fread($f,8192))
				$picture .= $r;
			fclose($f);

			$sql = "INSERT INTO ".PREFIX_DB."pictures
				(id_adh, picture, format, width, height)
				VALUES ('".$id."','',".$DB->Qstr($extension).",'1','1')";
			if (!$DB->Execute($sql))
				return false;
			if (!$DB->UpdateBlob(PREFIX_DB.'pictures','picture',$picture,'id_adh='.$id))
				return false;
			return true;
		}
	}
?>
