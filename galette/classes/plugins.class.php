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
 * plugins.class.php, 9 mars 2009
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
 * Plugins class for galette
 *
 * @name Plugins
 * @package Galette
 *
 */

class Plugins {
	protected $path;
	//protected $ns;
	protected $modules = array();
	protected $disabled = array();
	
	protected $id;
	protected $mroot;

	/**
	* Default constructor
	*/
	public function __construct(){}

	public function __get($name){
		/** TODO: What to do ? :-) */
	}


	/**
	Loads modules. <var>$path</var> could be a separated list of paths
	(path separator depends on your OS).
	
	@author Olivier Meunier and contributors

	<var>$ns</var> indicates if an additionnal file needs to be loaded on plugin
	load, value could be:
	- admin (loads module's _admin.php)
	- public (loads module's _public.php)
	- xmlrpc (loads module's _xmlrpc.php)
	
	<var>$lang</var> indicates if we need to load a lang file on plugin
	loading.
	*/
	public function loadModules($path, /*$ns=null,*/ $lang=null)
	{
		$this->path = explode(PATH_SEPARATOR,$path);
		//$this->ns = $ns;
		
		foreach ($this->path as $root) {
			if (!is_dir($root) || !is_readable($root)) {
				continue;
			}
			
			if (substr($root,-1) != '/') {
				$root .= '/';
			}
			
			if (($d = @dir($root)) === false) {
				continue;
			}
			
			while (($entry = $d->read()) !== false) {
				$full_entry = $root . $entry;
				
				if ($entry != '.' && $entry != '..' && is_dir($full_entry) && file_exists($full_entry.'/_define.php')) {
					if (!file_exists($full_entry.'/_disabled')) {
						$this->id = $entry;
						$this->mroot = $full_entry;
						require $full_entry . '/_define.php';
						$this->id = null;
						$this->mroot = null;
					} else {
						$this->disabled[$entry] = array(
							'root' => $full_entry,
							'root_writable' => is_writable($full_entry)
						);
					}
				}
			}
			$d->close();
		}
		
		# Sort plugins
		uasort( $this->modules, array($this, 'sortModules') );
		
		# Load translation, _prepend and ns_file
		foreach ($this->modules as $id => $m) {
			/*if (file_exists($m['root'].'/_prepend.php')) {
				$r = require $m['root'].'/_prepend.php';
				
				# If _prepend.php file returns null (ie. it has a void return statement)
				if (is_null($r)) {
					continue;
				}
				unset($r);
			}*/
			$this->loadModuleL10N($id,$lang,$id);
			$this->loadSmarties($id);
			/*if ($ns == 'admin') {
				$this->loadModuleL10Nresources($id,$lang);
			}
			$this->loadNsFile($id,$ns);*/
		}
	}

	/**
	This method registers a module in modules list. You should use this to
	register a new module.
	
	<var>$permissions</var> is a comma separated list of permissions for your
	module. If <var>$permissions</var> is null, only super admin has access to
	this module.
	
	<var>$priority</var> is an integer. Modules are sorted by priority and name.
	Lowest priority comes first.

	@author Olivier Meunier and contributors
	
	@param	name		<b>string</b>		Module name
	@param	desc		<b>string</b>		Module description
	@param	author		<b>string</b>		Module author name
	@param	version		<b>string</b>		Module version
	@param	permissions	<b>string</b>		Module permissions
	@param	priority	<b>integer</b>		Module priority
	*/
	public function register($name, $desc, $author, $version, $permissions=null, $priority=1000) {
		/*if ($this->ns == 'admin') {
			if ($permissions == '' && !$this->core->auth->isSuperAdmin()) {
				return;
			} elseif (!$this->core->auth->check($permissions,$this->core->blog->id)) {
				return;
			}
		}*/
		
		if ($this->id) {
			$this->modules[$this->id] = array(
			'root' => $this->mroot,
			'name' => $name,
			'desc' => $desc,
			'author' => $author,
			'version' => $version,
			'permissions' => $permissions,
			'priority' => $priority === null ? 1000 : (integer) $priority,
			'root_writable' => is_writable($this->mroot)
			);
		}
	}
	
	public function resetModulesList()
	{
		$this->modules = array();
	}	
	
	public function deactivateModule($id)
	{
		if (!isset($this->modules[$id])) {
			throw new Exception(_T('No such module.'));
		}
		
		if (!$this->modules[$id]['root_writable']) {
			throw new Exception(_T('Cannot deactivate plugin.'));
		}
		
		if (@file_put_contents($this->modules[$id]['root'].'/_disabled','')) {
			throw new Exception(_T('Cannot deactivate plugin.'));
		}
	}
	
	public function activateModule($id)
	{
		if (!isset($this->disabled[$id])) {
			throw new Exception(_T('No such module.'));
		}
		
		if (!$this->disabled[$id]['root_writable']) {
			throw new Exception(_T('Cannot activate plugin.'));
		}
		
		if (@unlink($this->disabled[$id]['root'].'/_disabled') === false) {
			throw new Exception(_T('Cannot activate plugin.'));
		}
	}
	
	/**
	This method will search for file <var>$file</var> in language
	<var>$lang</var> for module <var>$id</var>.
	
	<var>$file</var> should not have any extension.
	
	@param	id		<b>string</b>		Module ID
	@param	language	<b>string</b>		Language code
	@param	file		<b>string</b>		File name (without extension)
	*/
	public function loadModuleL10N($id,$language,$file)
	{
		global $lang;
		if (!$language || !isset($this->modules[$id])) {
			return;
		}

		$f = $this->modules[$id]['root'] . '/lang' . '/lang_' . $language . '.php';
		if( file_exists($f) )
	        	include( $f );

		/*$lfile = $this->modules[$id]['root'].'/locales/%s/%s';
		if (l10n::set(sprintf($lfile,$lang,$file)) === false && $lang != 'en') {
			l10n::set(sprintf($lfile,'en',$file));
		}*/
	}
	
	public function loadModuleL10Nresources($id,$lang)
	{
		if (!$lang || !isset($this->modules[$id])) {
			return;
		}
		
		$f = l10n::getFilePath($this->modules[$id]['root'].'/locales','resources.php',$lang);
		if ($f) {
			$this->loadModuleFile($f);
		}
	}

	/**
	Loads smarties specific (headers, assigments and so on)

	@param	id		<b>string</b>		Module ID
	*/
	public function loadSmarties($id){
		$f = $this->modules[$id]['root'] . '/_smarties.php';
		if( file_exists($f) ){
			require_once( $f );
			if( isset($_tpl_assignments) )
				$this->modules[$id]['tpl_assignments'] = $_tpl_assignments;
		}
	}

	/**
	Returns all modules associative array or only one module if <var>$id</var>
	is present.
	
	@param	id		<b>string</b>		Optionnal module ID
	@return	<b>array</b>
	*/
	public function getModules($id=null)
	{
		if ($id && isset($this->modules[$id])) {
			return $this->modules[$id];
		}
		return $this->modules;
	}
	
	/**
	Returns true if the module with ID <var>$id</var> exists.
	
	@param	id		<b>string</b>		Module ID
	@return	<b>boolean</b>
	*/
	public function moduleExists($id)
	{
		return isset($this->modules[$id]);
	}
	
	/**
	Returns all disabled modules in an array
	
	@return	<b>array</b>
	*/
	public function getDisabledModules()
	{
		return $this->disabled;
	}
	
	/**
	Returns root path for module with ID <var>$id</var>.
	
	@param	id		<b>string</b>		Module ID
	@return	<b>string</b>
	*/
	public function moduleRoot($id)
	{
		return $this->moduleInfo($id,'root');
	}
	
	/**
	Returns a module information that could be:
	- root
	- name
	- desc
	- author
	- version
	- permissions
	- priority
	
	@param	id		<b>string</b>		Module ID
	@param	info		<b>string</b>		Information to retrieve
	@return	<b>string</b>
	*/
	public function moduleInfo($id,$info)
	{
		return isset($this->modules[$id][$info]) ? $this->modules[$id][$info] : null;
	}

	/**
	* Search and load menu templates from plugins.
	* Also sets the web path to the plugin with the var "galette_[plugin-name]_path"
	*/
	public function getMenus(){
		global $tpl, $preferences;
		foreach(array_keys($this->getModules()) as $r){
			$menu_path = $this->getTemplatesPath($r) . '/menu.tpl';
			if( $tpl->template_exists( $menu_path ) ){
				$tpl->assign('galette_' . strtolower($r) . '_path', 'plugins/' . $r . '/');
				$tpl->display($menu_path);
			}
		}
	}
	
	private function sortModules($a,$b)
	{
		if ($a['priority'] == $b['priority']) {
			return strcasecmp($a['name'],$b['name']);
		}
		
		return ($a['priority'] < $b['priority']) ? -1 : 1;
	}

	/**
	* Get the templates path for a specified module
	* @param id		<b>string</b>	Module ID
	*/
	public function getTemplatesPath($id){
		global $preferences;
		return $this->moduleRoot($id) . '/templates/' . $preferences->pref_theme;
	}

	/**
	For each module, returns the headers.tpl full path, if present.
	*/
	public function getTplHeaders(){
		$_headers = array();
		foreach( $this->modules as $key=>$module ){
			$headers_path = $this->getTemplatesPath($key) . '/headers.tpl';
			if( file_exists( $headers_path ) )
				$_headers[] = $headers_path;
		}
		return $_headers;
	}

	/**
	For each module, gets templates assignements ; and replace some path variables
	*/
	public function getTplAssignments(){
		global $preferences;
		$_assign = array();
		foreach( $this->modules as $key=>$module ){
			if( isset($module['tpl_assignments']) )
				foreach( $module['tpl_assignments'] as $k=>$v ){
					$v = str_replace('__plugin_include_dir__', 'plugins/' . $key . '/includes/', $v);
					$v = str_replace('__plugin_templates_dir__', 'plugins/' . $key . '/templates/' . $preferences->pref_theme . '/', $v);
					$_assign[$k] = $v;
				}
		}
		return $_assign;
	}

	/* SETTERS */
	public function __set($name, $value){
		/** TODO: What to do ? :-) */
	}
}
?>
