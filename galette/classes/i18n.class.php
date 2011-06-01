<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2011 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

/**
 * i18n handling
 *
 * @category  Classes
 * @name      i18n
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

class I18n
{
    private $_id;
    private $_longid;
    private $_name;
    private $_abbrev;
    private $_flag;
    private $_filename;

    private $_langs;

    const DEFAULT_LANG = 'fr_FR';

    private $_dir = 'lang/';
    private $_path;
    private $_file = 'languages.xml';

    /**
    * Default constructor.
    * Initialize default language and set environment variables
    *
    * @param bool $lang true if there were a language change
    *
    * @return void
    */
    function __construct($lang = false)
    {
        $this->_path = WEB_ROOT . $this->_dir;
        $this->_file = $this->_path . $this->_file;

        if ( !$lang ) {
            $this->changeLanguage(self::DEFAULT_LANG);
        } else {
            $this->_load($lang);
        }
    }

    /**
    * Load language parameters from the XML file
    *
    * @param string $id Identifier forv requested language
    *
    * @return void
    */
    public function changeLanguage($id)
    {
        global $log;
        $log->log('Trying to set locale to ' . $id, PEAR_LOG_DEBUG);

        $xml = simplexml_load_file($this->_file);
        $current = $xml->xpath(
            '/translations/lang[@id=\'' . $id . '\'][not(@inactive)]'
        );

        //if no match, switch to default
        if ( !isset($current[0]) ) {
            $log->log(
                $id . ' does not exist in XML file, switching to default.',
                PEAR_LOG_WARNING
            );
            $id = self::DEFAULT_LANG;
            //do not forget to reload informations from the xml file
            $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        }

        $sxe = $current[0];
        $this->_id = $id;
        $this->_longid = ( isset($sxe['long']) )?(string)$sxe['long']:$id;
        $this->_name = (string)$sxe->longname;
        $this->_abbrev = (string)$sxe->shortname;
        $this->_flag = (string)$sxe->flag;
        $this->_filename = (string)$sxe->filename;
    }

    /**
    * Load a language
    *
    * @param string $id identifier for the language to load
    *
    * @return void
    */
    private function _load($id)
    {
        $xml = simplexml_load_file($this->_file);
        $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        $sxe = $current[0];
        $this->_id = $id;
        $this->_longid = ( isset($sxe['long']) )?(string)$sxe['long']:$id;
        $this->_name = (string)$sxe->longname;
        $this->_abbrev = (string)$sxe->shortname;
        $this->_flag = (string)$sxe->flag;
        $this->_filename = (string)$sxe->filename;
    }

    /**
    * List languages
    *
    * @return array list of all active languages
    */
    public function getList()
    {
        $result = array();
        $xml = simplexml_load_file($this->_file);
        foreach ( $xml->lang as $lang ) {
            if ( !$lang['inactive'] ) {
                $result[] = new I18n( $lang['id'] );
            }
        }

        return $result;
    }

    /**
    * Gets language full name from its ID
    *
    * @param string $id the language identifier
    *
    * @return string name for specified identifier
    */
    public function getNameFromId($id)
    {
        $xml = simplexml_load_file($this->_file);
        $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        $sxe = $current[0];
        return (string)$sxe->longname;
    }

    /**
    * Gets the language flag from its ID
    *
    * @param string $id the language identifier
    *
    * @return string path to flag for specified language identifier
    */
    public function getFlagFromId($id)
    {
        global $base_path, $template_subdir;
        $xml = simplexml_load_file($this->_file);
        $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        $sxe = $current[0];
        return $base_path . $template_subdir . 'images/' . $sxe->flag;
    }

    /**
    * Get current id
    *
    * @return string current language identifier
    */
    public function getID()
    {
        return $this->_id;
    }

    /**
    * Get long identifier
    *
    * @return string current language long identifier
    */
    public function getLongID()
    {
        return $this->_longid;
    }

    /**
    * Get current name
    *
    * @return string current language name
    */
    public function getName()
    {
        return $this->_name;
    }

    /**
    * Get current abreviation
    *
    * @return string current language abreviation
    */
    public function getAbbrev()
    {
        return $this->_abbrev;
    }

    /**
    * Get current flag
    *
    * @return string path to the current language flag image
    */
    public function getFlag()
    {
        global $base_path, $template_subdir;
        return $base_path . $template_subdir . 'images/' . $this->_flag;
    }

    /**
    * Get current filename
    *
    * @return string current filename
    */
    public function getFileName()
    {
        return $this->_filename;
    }
}
?>