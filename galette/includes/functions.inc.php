<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Utilities functions
 *
 * PHP version 5
 *
 * Copyright © 2003-2013 The Galette Team
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
 * @category  Functions
 * @package   Galette
 *
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

if (!defined('GALETTE_ROOT')) {
       die("Sorry. You can't access directly to this file");
}

use Analog\Analog as Analog;

/**
 * Check URL validity
 *
 * @param string $url The URL to check
 *
 * @return boolean
 */
function isValidWebUrl($url)
{
    return (preg_match(
        '#^http[s]?\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i',
        $url
    ));
}

function custom_html_entity_decode( $given_html, $quote_style = ENT_QUOTES )
{
    $trans_table = array_flip(get_html_translation_table(
        HTML_ENTITIES,
        $quote_style
    ));
    $trans_table['&#39;'] = "'";
    return (strtr($given_html, $trans_table ));
}

//TODO better handling (replace bad string not just detect it)
function sanityze_superglobals_arrays()
{
    $errors = 0;
    foreach ( $_GET as $k => $v ) {
        if (stripos("'",$v)!==false
            || stripos(";",$v)!==false
            || stripos("\"",$v)!==false
        ) {
             $errors++;
        }
    }
    foreach ( $_POST as $k => $v ) {
        if (stripos("'",$v)!==false
            || stripos(";",$v)!==false
            || stripos("\"",$v)!==false
        ) {
             $errors++;
        }
    }
    return $errors;
}

function date_db2text($date)
{
    if ($date != '')
    {
        list($a,$m,$j)=explode("-",$date);
        $date="$j/$m/$a";
    }
    return $date;
}

function date_text2db($DB, $date)
{
    list($j, $m, $a)=explode('/', $date);
    if ( !checkdate($m, $j, $a) ) {
        return '';
    }
    return $DB->DBDate($a.'-'.$m.'-'.$j);
}

function distance_months($beg, $end)
{
    list($bj, $bm, $ba) = explode('/', $beg);
    list($ej, $em, $ea) = explode('/', $end);
    if ( $bm > $em ) {
        $em += 12;
        $ea--;
    }
    return ($ea -$ba) * 12 + $em - $bm;
}

function beg_membership_after($date)
{
    global $preferences;
    $beg = "";
    if ( $preferences->pref_beg_membership != '' ) {
        list($j, $m) = explode('/', $preferences->pref_beg_membership);
        $time = mktime(0, 0, 0, $m, $j, $y);
        while ($time <= $date){
            $y++;
            $time = mktime(0, 0, 0, $m, $j, $y);
        }
        $beg = date('d/m/Y', strtotime('-1 day', $time)) . "\n";
    }
    return $beg;
}

/**
* Get a value sent by a form, either in POST and GET arrays
*
* @param string $name   property name
* @param string $defval default rollback value
*
* @return string value retrieved from :
* - GET array if defined and numeric,
* - POST array if defined and numéric
* - $defval otherwise
*/
function get_form_value($name, $defval)
{
    $val = $defval;
    if ( isset($_GET[$name]) ) {
        $val = $_GET[$name];
    } elseif ( isset($_POST[$name]) ) {
        $val = $_POST[$name];
    }
    return $val;
}

/**
* Get a numeric value sent by a form, either in POST and GET arrays
*
* @param string $name   property name
* @param string $defval default rollback value
*
* @return numeric value retrieved from :
* - GET array if defined and numeric,
* - POST array if defined and numéric
* - $defval otherwise
*/
function get_numeric_form_value($name, $defval)
{
    $val = get_form_value($name, $defval);
    if ( !is_numeric($val) ) {
        Analog::log(
            '[get_numeric_form_value] not a numeric value! (value was: `' .
            $val . '`)',
            Analog::INFO
        );
        $val = $defval;
    }
    return $val;
}

/**
* Get a post numeric value
*
* @param string $name   property name
* @param string $defval default rollback value
*
* @return string value retrieved from :
* - POST array if defined and numéric
* - $defval otherwise
*/
function get_numeric_posted_value($name, $defval)
{
    if ( isset($_POST[$name]) ) {
        $val = $_POST[$name];
        if ( is_numeric($val) ) {
            return $val;
        }
    }
    return $defval;
}
