<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Export
 *
 * Permet l'export de données au format CSV
 *
 * PHP version 5
 *
 * Copyright © 2009 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: export.php 535 2009-02-11 07:23:06Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-16
 */

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() ) {
    header('location: voir_adherent.php');
    die();
}

require_once WEB_ROOT . 'classes/csv.class.php';
$csv = new Csv();

$written = array();
$tables_list = $mdb->listTables();

if ( isset( $_POST['export_tables'] ) && $_POST['export_tables'] != '' ) {
    foreach ( $_POST['export_tables'] as $table) {
        $requete = 'SELECT * FROM ' . $table;
        $result = $mdb->query($requete);
        if ( MDB2::isError($requete) ) {
            return -1;
        }

        $filename = Csv::DEFAULT_DIRECTORY . $table . '_full.csv';
        $fp = fopen($filename, 'w');
        if ( $fp ) {
            $csv->export(
                $result,
                Csv::DEFAULT_SEPARATOR,
                Csv::DEFAULT_QUOTE,
                true,
                $fp
            );
            fclose($fp);
            $written[] = $filename;
        }
    }
}

if ( isset( $_POST['export_parameted'] ) && $_POST['export_parameted'] != '' ) {
    foreach ( $_POST['export_parameted'] as $p) {
        $written[] = $csv->runParametedExport($p);
    }
}

$parameted = $csv->getParametedExports();

$tpl->assign('tables_list', $tables_list);
$tpl->assign('written', $written);
$tpl->assign('parameted', $parameted);
$content = $tpl->fetch('export.tpl');
$tpl->assign('content', $content);

$tpl->display('page.tpl');

?>
