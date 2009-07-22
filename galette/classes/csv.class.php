<?php

// Copyright © 2009 Johan Cwiklinski
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
 * csv.class.php, 09 fevrier 2009
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */

/**
 * csv export for galette
 *
 * @name Csv
 * @package Galette
 *
 */

class Csv{
	const NEWLINE = "\r\n";
	const BUFLINES = 100;

	const DEFAULT_SEPARATOR = ',';
	const DEFAULT_QUOTE = '"';
	const DEFAULT_DIRECTORY = 'exports/';

	private $rs;
	private $separator;
	private $quote;
	private $escaped;
	private $file;
	private $result;
	private $current_line;
	private $fields;

	private $parameted_dir = 'config/';
	private $parameted_path;
	private $parameted_file = 'exports.xml';

	private $accepted_separators = array(
		',',
		';',
		'\t'
	);

	private $accepted_quotes = array(
		'"',
		"'"
	);

	/**
	* Default constructor
	*/
	public function __construct(){
		$this->parameted_path = WEB_ROOT . $this->parameted_dir;
		$this->parameted_file = $this->parameted_path . $this->parameted_file;
	}


	/**
	* Export MDB2 ResultSet to CSV
	* @param rs MDB2 ResultSet
	* @param separator The CSV separator (either '\t', ';' or ',' are accepted)
	* @param quote how does fields should be quoted
	* @param titles does export shows column titles or not. Defaults to false.
	* @param tofile export to a file on disk. A file pointer should be passed here. Defaults to false.
	*/
	function export(&$rs, $separator, $quote, $titles=false, $file=false){
		if (!$rs) return '';
		//switch back to the default separator if not in accepted_separators array
		if( !in_array($separator, $this->accepted_separators) ) $separator = self::DEFAULT_SEPARATOR;
		//switch back to the default quote if not in accepted_quotes array
		if( !in_array($quote, $this->accepted_quotes) ) $quote = self::DEFAULT_QUOTE;
	
		$this->result = '';
		$this->rs = $rs;
		$this->max = $this->rs->NumRows();
		$this->separator = $separator;
		$this->quote = $quote;
		//dubbing quote for escaping
		$this->escaped = $quote . $quote;
		$this->file = $file;
		$this->current_line = 0;

		$fields = array();
		if( $titles && !count($titles>1) ){
			foreach( $rs->getColumnNames() as $field=>$position ){
				$fields[] = $this->quote . str_replace($this->quote, $this->escaped, $field) . $this->quote;
			}
			$this->result .= implode( $this->separator, $fields ) . self::NEWLINE;
		}else if( $titles && is_array($titles) && count($titles)>1 ){
			foreach( $titles as $field ){
				$fields[] = $this->quote . str_replace($this->quote, $this->escaped, $field) . $this->quote;
			}
			$this->result .= implode( $this->separator, $fields ) . self::NEWLINE;
		}

		while ($row = $this->rs->fetchRow(MDB2_FETCHMODE_ASSOC)){
			$elts = array();
	
			foreach($row as $k => $v){
				$elts[] = $this->quote . str_replace($this->quote, $this->escaped, $v) . $this->quote;
			}
	
			$this->result .= implode($this->separator, $elts) . self::NEWLINE;
	
			$this->current_line += 1;

			$this->write();
		}
		$this->write(true);
		return $this->result;
	}

	/**
	* Write export.
	* If a file is defined, export will be outpoutted into it. If not, it will be returned
	*/
	private function write($last=false){
		if( $last && $this->file || !$last && $this->file && ($this->current_line % self::BUFLINES) == 0){
			if ($this->file === true) echo $this->result;
			else fwrite($this->file, $this->result);
			$this->result = '';
		}
	}

	public function getParametedExports(){
		$parameted = array();

		$xml = simplexml_load_file($this->parameted_file);

		foreach( $xml->export as $export){
			if( !($export['inactive'] == 'inactive') ){
				$parameted[] = array(
					'id'	=>	(string)$export['id'],
					'name'	=>	(string)$export['name']
				);
			}
		}

		return $parameted;
	}

	/**
	* Run selected export
	* @param id export's id to run
	*/
	public function runParametedExport($id){
		global $mdb;

		$xml = simplexml_load_file($this->parameted_file);

		$xpath = $xml->xpath('/exports/export[@id=\'' . $id . '\'][not(@inactive)][1]');
		$export = $xpath[0];

		$result = $mdb->query( $export->query );
		if( MDB2::isError($result) )
			return -1;

		$filename=self::DEFAULT_DIRECTORY . $export['filename'];
		
		$fp = fopen($filename, 'w');
		if( $fp ){
			$separator = ( $export->separator ) ? $export->separator : self::DEFAULT_SEPARATOR;
			$quote = ( $export->quote ) ? $export->quote : self::DEFAULT_QUOTE;
			if( $export->headers->none ) {
				//No title
				$title = false;
			} else {
				$xpath = $export->xpath('headers/header');
				if( count($xpath) == 0 ){
					//show titles
					$title = true;
				} else {
					//titles from array
					foreach( $xpath as $header ){
						$title[] = (string)$header;
					}
				}
			}

			$this->export($result, $separator, $quote, $title, $fp);
			fclose($fp);
		} else {
			return false;
		}
		return $filename;
	}

	/* GETTERS */
	public function getAcceptedSeparators(){ return $this->accepted_separators; }
	public function getAcceptedQuotes(){ return $this->accepted_quotes; }
}
?>