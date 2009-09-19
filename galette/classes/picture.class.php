<?php

// Copyright © 2006 Frédéric Jaqcuot
// Copyright © 2007-2009 Johan Cwiklinski
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
 * Picture handling
 *
 * @package Galette
 * 
 * @author     Frédéric Jaqcuot
 * @copyright  2006 Frédéric Jaqcuot
 * @copyright  2007-2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 */

class Picture{
	const INVALID_FILE = -1;
	const FILE_TOO_BIG = -2;
	const MIME_NOT_ALLOWED = -3;
	const SQL_ERROR = -4;
	const SQL_BLOB_ERROR = -5;
	const MAX_FILE_SIZE = 1024;

	const TABLE = 'pictures';
	const PK = Adherent::PK;

	//private $bad_chars = array('\.', '\\\\', "'", ' ', '\/', ':', '\*', '\?', '"', '<', '>', '|');
	//array keys contain litteral value of each forbidden character (to be used when showing an error). Maybe is there a better way to handle this...
	private $bad_chars = array(
		'.'	=>	'\.', 
		'\\'	=>	'\\\\', 
		"'"	=>	"'", 
		' '	=>	' ', 
		'/'	=>	'\/', 
		':'	=>	':', 
		'*'	=>	'\*', 
		'?'	=>	'\?', 
		'"'	=>	'"', 
		'<'	=>	'<', 
		'>'	=>	'>', 
		'|'	=>	'|'
	);
	private $allowed_extensions = array('jpeg', 'jpg', 'png', 'gif');
	private $allowed_mimes = array(
				'jpg'	=>	'image/jpeg',
				'png'	=>	'image/png',
				'gif'	=>	'image/gif'
			);

	private $error = null;

	protected $id;
	protected $height;
	protected $width;
	protected $optimal_height;
	protected $optimal_width;
	protected $file_path;
	protected $format;
	protected $mime;
	protected $has_picture = true;
	protected $store_path = '../photos/';
	protected $max_width = 200;
	protected $max_height = 200;
	protected $custom = true;

	/**
	* Default constructor.
	* @param int id_adh the id of the member
	*/
	public function __construct( $id_adh='' ){
		// '!==' needed, otherwise ''==0
		if ($id_adh!==''){
			$this->id = $id_adh;

			if ( !$this->checkFileOnFS() ) { //if file does not exists on the FileSystem, check for it in the database
				$this->checkFileInDB();
			}
		}

		// if we still have no picture, take the default one
		if ( $this->file_path=='' ){
			$this->getDefaultPicture();
		}

		if( $this->file_path !== '' ) //we should not have an empty file_path, but...
			$this->setSizes();
	}

	/**
	* "Magic" function called on unserialize
	*/
	public function __wakeup(){
		//if file has been deleted since we store our object in the session, we try to retrieve it
		if ( !$this->checkFileOnFS() ) { //if file does not exists on the FileSystem, check for it in the database
			$this->checkFileInDB();
		}

		// if we still have no picture, take the default one
		if ( $this->file_path=='' ){
			$this->getDefaultPicture();
		}

		if( $this->file_path !== '' ) //we should not have an empty file_path, but...
			$this->setSizes();
	}

	/**
	* Check if current file is present on the File System
	*/
	private function checkFileOnFS(){
		if (file_exists(dirname(__FILE__).'/' . $this->store_path . $this->id . '.jpg')){
			$this->file_path = dirname(__FILE__).'/' . $this->store_path . $this->id . '.jpg';
			$this->format = 'jpg';
			$this->mime = 'image/jpeg';
			return true;
		} elseif (file_exists(dirname(__FILE__).'/' . $this->store_path . $this->id . '.png')) {
			$this->file_path = dirname(__FILE__).'/' . $this->store_path . $this->id . '.png';
			$this->format = 'png';
			$this->mime = 'image/png';
			return true;
		} elseif (file_exists(dirname(__FILE__).'/' . $this->store_path . $this->id . '.gif')) {
			$this->file_path = dirname(__FILE__).'/' . $this->store_path . $this->id . '.gif';
			$this->format = 'gif';
			$this->mime = 'image/gif';
			return true;
		}
		return false;
	}

	/**
	* Check if current file is present in the database, and copy it to the File System
	*/
	private function checkFileInDB(){
		global $mdb;
		$sql = $this->getCheckFileQuery();

		$result = $mdb->query($sql);
		if( MDB2::isError($result) ){
			return false;
		}

		if( $result->numRows() > 0 ){
			// we must regenerate the picture file
			$pic = $result->fetchRow();
			$f = fopen(dirname(__FILE__).'/' . $this->store_path . $this->id . '.' . $pic->format, 'wb');
			fwrite ($f, $pic->picture);
			fclose($f);
			$this->format = $pic->format;
			switch($this->format) {
				case 'jpg':
					$this->mime = 'image/jpeg';
					break;
				case 'png':
					$this->mime = 'image/png';
					break;
				case 'gif':
					$this->mime = 'image/gif';
					break;
			}
			$this->file_path = dirname(__FILE__).'/' . $this->store_path . $this->id . '.' . $this->format;
			return true;
		}
		return false;
	}

	/**
	* Returns the relevant query to check if picture exists in database.
	*/
	protected function getCheckFileQuery(){
		return 'SELECT picture, format FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=\'' . $this->id . '\'';
	}

	/**
	* Gets the default picture to show, anyways
	*/
	protected function getDefaultPicture(){
		$this->file_path = _current_template_path . 'images/default.png';
		$this->format = 'png';
		$this->mime = 'image/png';
		$this->has_picture = false;
	}

	/**
	* Set picture sizes
	*/
	private function setSizes(){
		list($width, $height) = getimagesize($this->file_path);
		$this->height = $height;
		$this->width = $width;
		$this->optimal_height = $height;
		$this->optimal_width = $width;

		if ($this->height > $this->width){
			if ($this->height > $this->max_height){
				$ratio = $this->max_height / $this->height;
				$this->optimal_height = $this->max_height;
				$this->optimal_width = $this->width * $ratio;
			}
		} else {
			if ($this->width > $this->max_width){
				$ratio = $this->max_width / $this->width;
				$this->optimal_width = $this->max_width;
				$this->optimal_height = $this->height * $ratio;
			}
		}
	}

	/**
	* Set header and displays the picture.
	*/
	public function display(){
		header('Content-type: '.$this->mime);
		readfile($this->file_path);
	}

	/**
	* Deletes a picture, from both database and filesystem
	* @param int id identifiant for the picture to delete
	*/
	public function delete(){
		global $mdb;
		$sql = 'DELETE FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=\'' . $this->id . '\'';
		$result = $mdb->query($sql);
		if( MDB2::isError($result) ){
			return false;
		} else {
			if (file_exists(dirname(__FILE__).'/' . $this->store_path . $this->id . '.jpg'))
				return unlink(dirname(__FILE__).'/' . $this->store_path . $this->id . '.jpg');
			elseif (file_exists(dirname(__FILE__).'/' . $this->store_path . $this->id . '.png'))
				return unlink(dirname(__FILE__).'/' . $this->store_path . $this->id . '.png');
			elseif (file_exists(dirname(__FILE__).'/' . $this->store_path . $this->id . '.gif'))
				return unlink(dirname(__FILE__).'/' . $this->store_path . $this->id . '.gif');
		}
		return true;
	}

	public function store($file){
		/** TODO:
			- make upload dir configurable
			- fix max size (by preferences ?)
			- make possible to store images in database, filesystem or both
		*/
		global $mdb, $log;

		$name = $file['name'];
		$tmpfile = $file['tmp_name'];

		//First, does the file have a valid name?
		$reg = "/^(.[^" . implode('', $this->bad_chars) . "]+)\.(" . implode('|', $this->allowed_extensions) . ")$/i";
		if( preg_match( $reg, $name, $matches ) ){
			$log->log('Filename and extension are OK, proceed.', PEAR_LOG_DEBUG);
			$extension = $matches[2];
		} else {
			$log->log('Invalid filename or extension.', PEAR_LOG_ERR);
			return self::INVALID_FILE;
		}

		//Second, let's check file size
		if( $file['size'] > ( self::MAX_FILE_SIZE * 1024 ) ){
			$log->log('File is too big (' . ( $file['size'] * 1024 ) . 'Ko for maximum authorized ' . ( self::MAX_FILE_SIZE * 1024 ) . 'Ko', PEAR_LOG_ERR);
			return self::FILE_TOO_BIG;
		} else {
			$log->log('Filesize is OK, proceed', PEAR_LOG_DEBUG);
		}

		$current = getimagesize($tmpfile);

		if( !in_array($current['mime'], $this->allowed_mimes) ){
			$log->log('Mimetype not allowed', PEAR_LOG_ERR);
			return self::MIME_NOT_ALLOWED;
		} else {
			$log->log('Mimetype is allowed, proceed', PEAR_LOG_DEBUG);
		}

		$this->delete();

		$new_file = dirname(__FILE__).'/' . $this->store_path . $this->id . '.' . $extension;
		move_uploaded_file($tmpfile, $new_file);

		// current[0] gives width ; current[1] gives height
		if( $current[0] > $this->max_width || $current[1] > $this->max_height ){
			resizeimage($new_file, $new_file, $this->max_width, $this->max_height);
		}

		//store file in database
		$f = fopen($new_file, 'r');
		$picture = '';
		while ($r=fread($f,8192))
			$picture .= $r;
		fclose($f);

		$stmt = $mdb->prepare(
				'INSERT INTO ' . PREFIX_DB . self::TABLE . ' (' . self::PK . ', picture, format) VALUES (:id, :picture, :extension)',
				array('integer', 'blob', 'text'),
				MDB2_PREPARE_MANIP,
				array('picture')
			);

		$stmt->bindParam(0, $this->id);
		$stmt->bindParam(1, $picture);
		$stmt->bindParam(2, $extension);
		$stmt->execute();

		if( MDB2::isError($stmt) ){
			$log->log('An error has occured inserting picture in database | ' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_ERR);
			return self::SQL_ERROR;
		}
		$stmt->free();
		return true;
	}

	/* GETTERS */
	/**
	* Returns current file optimal height (resized)
	*/
	public function getOptimalHeight(){
		return $this->optimal_height;
	}

	/**
	* Returns current file height
	*/
	public function getHeight(){
		return $this->height;
	}

	/**
	* Returns current file optimal width (resized)
	*/
	public function getOptimalWidth(){
		return $this->optimal_width;
	}

	/**
	* Returns current file width
	*/
	public function getWidth(){
		return $this->width;
	}

	/**
	* Returns current file format
	*/
	public function getFormat(){
		return $this->format;
	}

	/**
	* True if a picture matches adherent's id, false otherwise
	*/
	public function hasPicture(){
		return $this->has_picture;
	}

	/**
	* Returns unauthorized characters litteral values quoted, comma separated values
	*/
	public function getBadChars(){
		$ret = '';
		foreach( $this->bad_chars as $char=>$regchar){
			$ret .= '`' . $char . '`, ';
		}
		return $ret;
	}

	/**
	* Returns allowed extensions, comma separated values
	*/
	public function getAllowedExts(){
		return implode(', ', $this->allowed_extensions);
	}

	/**
	* Return the array of allowed mime types
	*/
	public function getAllowedMimeTypes(){
		return $this->allowed_mimes;
	}

	/**
	* Returns current file full path
	*/
	public function getPath(){
		return $this->file_path;
	}

	/**
	* Returns custom state
	*/
	public function isCustom(){
		return $this->custom;
	}
}
?>
