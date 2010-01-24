<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * database.inc.php
 *
 * PHP version 5
 *
 * Copyright © 2007-2010 The Galette Team
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
 * @author    Steve Gricci <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @site      http://www.deepcode.net
 */

function utime ()
{
    $time = explode( " ", microtime());
    $usec = (double)$time[0];
    $sec = (double)$time[1];
    return $sec + $usec;
}
$start = utime();

include WEB_ROOT . '/includes/adodb' . ADODB_VERSION . '/adodb.inc.php';
$DB = ADONewConnection(TYPE_DB);
$DB->debug = false;
if ( !@$DB->Connect(HOST_DB, USER_DB, PWD_DB, NAME_DB) ) {
    die('No database connection...');
}

if ( !defined('PREFIX_DB') ) {
    define('PREFIX_DB', '');
}

// Definition du protocole
if ( isset($_SERVER['HTTPS']) ) {
    if ( $_SERVER['HTTPS'] == 'on') {
        define('HTTP', 'https');
    } else {
        define('HTTP', 'http');
    }
} else {
    define('HTTP', 'http');
}

/** FIXME: preferences are now owned by a class. All PREF_ constants would
be replaced soon, the following lines will dissapear too */
// Chargement des preferences
$result = $DB->Execute('SELECT * FROM ' . PREFIX_DB . 'preferences');
while ( !$result->EOF ) {
    define(strtoupper($result->fields["nom_pref"]), $result->fields["val_pref"]);
    $result->MoveNext();
}
$result->Close();

function is_exempt($DB, $cotisant)
{
    $requete_cotis = 'SELECT bool_exempt_adh FROM ' . PREFIX_DB .
        'adherents WHERE id_adh=' . $cotisant;
    return $DB->GetOne($requete_cotis);
}

function get_echeance($DB, $cotisant)
{
    $exempt = is_exempt($DB, $cotisant);

    $return_date = '';
    // définition couleur pour adherent exempt de cotisation
    if ( $exempt != '1' ) {
        $requete_cotis = 'SELECT count(*) FROM ' . PREFIX_DB .
            'cotisations WHERE id_adh=' . $cotisant;
        $count = $DB->GetOne($requete_cotis);
        if ( $count ) {
            $requete_cotis = 'SELECT max(date_fin_cotis) FROM ' . PREFIX_DB .
                'cotisations WHERE id_adh=' . $cotisant;
            $max_date = $DB->GetOne($requete_cotis);
            if ($max_date) {
                list($a, $m, $j) = explode('-', $max_date);
                $return_date = explode(
                    "/",
                    date('d/m/Y', mktime(0, 0, 0, $m, $j, $a))
                );
            }
        }
    }
    return $return_date;
}

function get_last_auto_increment($DB, $table, $column)
{
    $val_or_oid = $DB->Insert_ID();
    // When calling Insert_ID, postgres returns the OID
    $val = $DB->GetOne("SELECT $column FROM $table WHERE oid=$val_or_oid");
    return $val ? $val : $val_or_oid;
}

function parse_db_result($DB, $result, $error_detected, $query)
{
    if ( $result == false ) {
        /** FIXME will that work if $error_detected is not global ? */
        $error_detected[] = _T("- SQL error: ")."[$query]".$DB->ErrorMsg();
    }
    return $result;
}

function db_execute($DB, $query, $error_detected)
{
    return parse_db_result(
        $DB,
        $DB->Execute($query),
        $error_detected,
        $query
    );
}

function db_get_one($DB, $query, $error_detected)
{
    return parse_db_result(
        $DB,
        $DB->GetOne($query),
        $error_detected,
        $query
    );
}

function db_get_row($DB, $query, $error_detected)
{
    return parse_db_result(
        $DB,
        $DB->GetRow($query),
        $error_detected,
        $query
    );
}

function db_get_all($DB, $query, $error_detected)
{
    return parse_db_result(
        $DB,
        $DB->GetAll($query),
        $error_detected,
        $query
    );
}

function db_boolean($val)
{
    return $val ? "'1'" : "NULL";
}
?>
