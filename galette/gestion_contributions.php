<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions management
 *
 * PHP version 5
 *
 * Copyright © 2004-2010 The Galette Team
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
 * @author    Frédéric Jacquot <unknown@unknwown.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}

$filtre_id_adh = '';

if ( !$login->isAdmin() ) {
    $_SESSION['filtre_cotis_adh'] = $login->id;
} else {
    if ( isset($_GET['id_adh']) ) {
        if ( is_numeric($_GET['id_adh']) ) {
            $_SESSION['filtre_cotis_adh'] = $_GET['id_adh'];
        } else {
            $_SESSION['filtre_cotis_adh'] = '';
        }
    }
}

$numrows = $preferences->pref_numrows;

if ( isset($_GET['nbshow']) ) {
    if ( is_numeric($_GET['nbshow']) ) {
        $numrows = $_GET['nbshow'];
    }
}

if ( isset($_GET['contrib_filter_1']) ) {
    if ( preg_match("@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@", $_GET['contrib_filter_1'], $array_jours) ) {
        if ( checkdate($array_jours[2], $array_jours[1], $array_jours[3]) ) {
            $_SESSION['filtre_date_cotis_1'] = $_GET['contrib_filter_1'];
        } else {
            $error_detected[] = _T("- Non valid date!");
        }
    } elseif ( preg_match("/^([0-9]{4})$/", $_GET['contrib_filter_1'], $array_jours) ) {
        $_SESSION["filtre_date_cotis_1"]="01/01/".$array_jours[1];
    } elseif ( $_GET['contrib_filter_1'] == '' ) {
        $_SESSION['filtre_date_cotis_1'] = '';
    } else {
        $error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");
    }
}

if ( isset($_GET['contrib_filter_2']) ) {
    if ( preg_match("@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@", $_GET['contrib_filter_2'], $array_jours) ) {
        if ( checkdate($array_jours[2], $array_jours[1], $array_jours[3]) ) {
            $_SESSION['filtre_date_cotis_2'] = $_GET['contrib_filter_2'];
        } else {
            $error_detected[] = _T("- Non valid date!");
        }
    } elseif ( preg_match("/^([0-9]{4})$/", $_GET['contrib_filter_2'], $array_jours) ) {
        $_SESSION['filtre_date_cotis_2'] = '01/01/' . $array_jours[1];
    } elseif ( $_GET['contrib_filter_2'] == '') {
        $_SESSION['filtre_date_cotis_2'] = '';
    } else {
        $error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");
    }
}

$page = 1;
if ( isset($_GET['page']) ) {
    $page = $_GET['page'];
}

// Tri
if ( isset($_GET['tri']) ) {
    if ( $_SESSION['tri_cotis'] == $_GET['tri'] ) {
        $_SESSION['tri_cotis_sens'] = ($_SESSION['tri_cotis_sens'] + 1) % 2;
    } else {
        $_SESSION["tri_cotis"]=$_GET["tri"];
        $_SESSION["tri_cotis_sens"]=0;
    }
}

if ( $login->isAdmin() ) {
    if ( isset($_GET['sup']) ) {
        // recherche adherent
        $requetesel = 'SELECT id_adh FROM ' . PREFIX_DB .
            'cotisations WHERE id_cotis=' .
            $DB->qstr($_GET['sup'], get_magic_quotes_gpc());
        $result_adh = &$DB->Execute($requetesel);
        if ( !$result_adh->EOF ) {
            $id_adh = $result_adh->fields['id_adh'];

            $requetesup = 'SELECT nom_adh, prenom_adh FROM ' . PREFIX_DB .
                'adherents WHERE id_adh=' .
                $DB->qstr($id_adh, get_magic_quotes_gpc());
            $resultat = $DB->Execute($requetesup);
            if ( !$resultat->EOF ) {
                // supression record cotisation
                $requetesup = 'DELETE FROM ' . PREFIX_DB .
                    'cotisations WHERE id_cotis=' .
                    $DB->qstr($_GET['sup'], get_magic_quotes_gpc());
                $DB->Execute($requetesup);

                // mise a jour de l'échéance
                $date_fin = get_echeance($DB, $id_adh);
                if ( $date_fin != '' ) {
                    $date_fin_update = '\'' . $date_fin[2] . '-' .
                        $date_fin[1] . '-' . $date_fin[0] . '\'';
                } else {
                    $date_fin_update = 'NULL';
                }
                $requeteup = 'UPDATE ' . PREFIX_DB .
                    'adherents SET date_echeance=' . $date_fin_update .
                    ' WHERE id_adh=' .
                    $DB->qstr($id_adh, get_magic_quotes_gpc());
                $DB->Execute($requeteup);
                $hist-add(
                    'Contribution deleted:',
                    strtoupper($resultat->fields[0]) . ' ' . $resultat->fields[1],
                    $requetesup
                );
            }
            $resultat->Close();
        }
        $result_adh->Close();
    }
}

$date_enreg_format = $DB->SQLDate('d/m/Y', PREFIX_DB . 'cotisations.date_enreg');
$date_debut_cotis_format = $DB->SQLDate(
    'd/m/Y',
    PREFIX_DB . 'cotisations.date_debut_cotis'
);
$date_fin_cotis_format = $DB->SQLDate(
    'd/m/Y',
    PREFIX_DB . 'cotisations.date_fin_cotis'
);
$requete[0] = "SELECT $date_enreg_format AS date_enreg,
                $date_debut_cotis_format AS date_debut_cotis,
                $date_fin_cotis_format AS date_fin_cotis,
                " . PREFIX_DB . "cotisations.id_cotis,
                " . PREFIX_DB . "cotisations.id_adh,
                " . PREFIX_DB . "cotisations.montant_cotis,
                " . PREFIX_DB . "adherents.nom_adh,
                " . PREFIX_DB . "adherents.prenom_adh,
                " . PREFIX_DB . "types_cotisation.libelle_type_cotis,
                " . PREFIX_DB . "types_cotisation.cotis_extension
                FROM " . PREFIX_DB . "cotisations," . PREFIX_DB . "adherents," . PREFIX_DB . "types_cotisation
                WHERE " . PREFIX_DB . "cotisations.id_adh=" . PREFIX_DB . "adherents.id_adh
                AND " . PREFIX_DB . "types_cotisation.id_type_cotis=" . PREFIX_DB . "cotisations.id_type_cotis ";
$requete[1] = 'SELECT count(id_cotis) FROM ' . PREFIX_DB . 'cotisations WHERE 1=1 ';

// phase filtre
if ( $_SESSION['filtre_cotis_adh'] != '' ) {
    $qry = 'AND ' . PREFIX_DB . 'cotisations.id_adh=\'' .
        $_SESSION['filtre_cotis_adh'] . '\' ';
    $requete[0] .= $qry;
    $requete[1] .= $qry;
}

// date filter
if ( $_SESSION['filtre_date_cotis_1'] != '') {
    preg_match(
        "@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@",
        $_SESSION['filtre_date_cotis_1'],
        $array_jours
    );
    $datemin = '\'' . $array_jours[3] . '-' . $array_jours[2] . '-' .
        $array_jours[1] . '\'';
    $qry = 'AND ' . PREFIX_DB . 'cotisations.date_debut_cotis >= ' .
        $datemin . ' ';
    $requete[0] .= $qry;
    $requete[1] .= $qry;
}
if ( $_SESSION['filtre_date_cotis_2'] != '' ) {
    preg_match(
        "@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@",
        $_SESSION['filtre_date_cotis_2'],
        $array_jours
    );
    $datemax = '\'' . $array_jours[3] . '-' . $array_jours[2] . '-' .
        $array_jours[1] . '\'';
    $qry = 'AND ' . PREFIX_DB . 'cotisations.date_debut_cotis <= ' . $datemax . ' ';
    $requete[0] .= $qry;
    $requete[1] .= $qry;
}

// phase de tri
if ( $_SESSION['tri_cotis_sens'] == '0') {
    $tri_cotis_sens_txt = 'ASC';
} else {
    $tri_cotis_sens_txt = 'DESC';
}

$requete[0] .= 'ORDER BY ';

// tri par adherent
if ( $_SESSION['tri_cotis'] == '1' ) {
    $requete[0] .= 'nom_adh ' . $tri_cotis_sens_txt . ', prenom_adh ' .
        $tri_cotis_sens_txt . ',';
} elseif ( $_SESSION['tri_cotis'] == '2' ) {// tri par type
    $requete[0] .= 'libelle_type_cotis ' . $tri_cotis_sens_txt . ',';
} elseif ( $_SESSION['tri_cotis'] == '3' ) {// tri par montant
    $requete[0] .= 'montant_cotis ' . $tri_cotis_sens_txt . ',';
} elseif ( $_SESSION['tri_cotis'] == '4' ) {// tri par duree
    $requete[0] .= '(date_fin_cotis - date_debut_cotis) ' .
        $tri_cotis_sens_txt . ',';
}

// defaut : tri par date
$requete[0] .= ' ' . PREFIX_DB . 'cotisations.date_debut_cotis ' .
    $tri_cotis_sens_txt;

if ( $numrows==0 ) {
    $resultat = &$DB->Execute($requete[0]);
} else {
    $resultat = &$DB->SelectLimit($requete[0], $numrows, ($page-1)*$numrows);
}

$nb_contributions = $DB->GetOne($requete[1]);
$contributions = array();

if ( $numrows==0 ) {
    $nbpages = 1;
} else if ( $nb_contributions % $numrows == 0 ) {
    $nbpages = intval($nb_contributions/$numrows);
} else {
    $nbpages = intval($nb_contributions/$numrows)+1;
}
if ( $nbpages==0 ) {
    $nbpages = 1;
}

$compteur = 1+($page-1)*$numrows;
while ( !$resultat->EOF ) {
    if ( $resultat->fields['date_fin_cotis'] != $resultat->fields['date_debut_cotis'] ) {
        $row_class = "cotis-normal";
    } else {
        $row_class = "cotis-give";
    }
    $is_cotis = ($resultat->fields['cotis_extension'] == '1');
    $contributions[$compteur]['class'] = $row_class;
    $contributions[$compteur]['id_cotis'] = $resultat->fields['id_cotis'];
    $contributions[$compteur]['date_enreg'] = $resultat->fields['date_enreg'];
    $contributions[$compteur]['date_debut'] = $resultat->fields['date_debut_cotis'];
    $contributions[$compteur]['date_fin'] = $is_cotis ?
        $resultat->fields['date_fin_cotis']
        : '';
    $contributions[$compteur]['id_adh'] = $resultat->fields['id_adh'];
    $contributions[$compteur]['nom'] = strtoupper($resultat->fields['nom_adh']);
    $contributions[$compteur]['prenom'] = $resultat->fields['prenom_adh'];
    $contributions[$compteur]['libelle_type_cotis']
        = _T($resultat->fields['libelle_type_cotis']);
    $contributions[$compteur]['montant_cotis'] = $resultat->fields['montant_cotis'];
    $contributions[$compteur]['duree_mois_cotis'] = $is_cotis ?
        distance_months(
            $resultat->fields['date_debut_cotis'],
            $resultat->fields['date_fin_cotis']
        )
        : "";
    $compteur++;
    $resultat->MoveNext();
}
$resultat->Close();

// if viewing a member's contributions, show deadline
if ( $_SESSION['filtre_cotis_adh'] != '' ) {
    $requete = 'SELECT date_echeance, bool_exempt_adh FROM ' . PREFIX_DB .
        'adherents WHERE id_adh=\'' . $_SESSION['filtre_cotis_adh'] . '\'';
    $resultat = $DB->Execute($requete);
    if ( $resultat->fields[1] ) {
        $statut_cotis = _T("Freed of dues");
        $statut_class = 'cotis-exempt';
    } else {
        if ( $resultat->fields[0] == '' ) {
            $statut_cotis = _T("Never contributed");
            $statut_class = 'cotis-never';
        } else {
            $date_fin = explode('-', $resultat->fields[0]);
            $ts_date_fin = mktime(0, 0, 0, $date_fin[1], $date_fin[2], $date_fin[0]);
            $aujourdhui = time();
            $difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
            if ($difference==0) {
                $statut_cotis = _T("Last day!");
                $statut_class = 'cotis-lastday';
            } elseif ($difference<0) {
                $statut_cotis = _T("Late of") . ' ' . -$difference . ' ' .
                    _T("days") . ' (' . _T("since") . ' ' . $date_fin[2] .
                    '/' . $date_fin[1] . '/' . $date_fin[0] . ')';
                $statut_class = 'cotis-late';
            } else {
                if ( $difference != 1 ) {
                    $statut_cotis = $difference . ' ' . _T("remaining days") .
                        ' (' . _T("ending on") . ' ' . $date_fin[2] . '/' .
                        $date_fin[1] . '/' . $date_fin[0] . ')';
                } else {
                    $statut_cotis = $difference . ' ' . _T("remaining day") .
                        ' (' . _T("ending on") . ' ' . $date_fin[2] . '/' .
                        $date_fin[1] . '/' . $date_fin[0] . ')';
                }
                if ( $difference < 30 ) {
                    $statut_class = 'cotis-soon';
                } else {
                    $statut_class = 'cotis-ok';
                }
            }
        }
    }
    $tpl->assign('statut_cotis', $statut_cotis);
    $tpl->assign('statut_class', $statut_class);
}


$tpl->assign('require_dialog', true);
$tpl->assign('contributions', $contributions);
$tpl->assign('nb_contributions', $nb_contributions);
$tpl->assign('nb_pages', $nbpages);
$tpl->assign('page', $page);
$tpl->assign(
    'filtre_options',
    array(
        0 => _T("All members"),
        3 => _T("Members up to date"),
        1 => _T("Close expiries"),
        2 => _T("Latecomers")
    )
);
$tpl->assign(
    'filtre_2_options',
    array(
        0 => _T("All the accounts"),
        1 => _T("Active accounts"),
        2 => _T("Inactive accounts")
    )
);
$tpl->assign(
    'nbshow_options',
    array(
        10  => '10',
        20  => '20',
        50  => '50',
        100 => '100',
        0   => _T("All")
    )
);
$tpl->assign('numrows', $numrows);
$content = $tpl->fetch('gestion_contributions.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
