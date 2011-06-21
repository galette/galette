<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add a new contribution
 *
 * PHP version 5
 *
 * Copyright © 2004-2011 The Galette Team
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
 * @copyright 2004-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
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

require_once 'classes/texts.class.php';
require_once 'includes/dynamic_fields.inc.php';

/**
 * TODO
 *
 * @param <type> $DB             TODO
 * @param <type> $trans_id       TODO
 * @param <type> $error_detected TODO
 *
 * @return <type>                TODO
 */
function missingContribAmount($DB, $trans_id, $error_detected)
{
    if (is_numeric($trans_id)) {
        $total_amount = db_get_one(
            $DB, 'SELECT trans_amount FROM ' . PREFIX_DB .
            'transactions WHERE trans_id=' . $trans_id,
            $error_detected
        );
        $current_amount = $DB->GetOne(
            'SELECT SUM(montant_cotis) FROM ' . PREFIX_DB .
            'cotisations WHERE trans_id=' . $trans_id
        );
        return $total_amount - $current_amount;
    }
    return 0;
}

// new or edit
$contribution['id_cotis'] = get_numeric_form_value('id_cotis', '');
$contribution['id_type_cotis'] = get_numeric_form_value('id_type_cotis', '');
$contribution['id_adh'] = get_numeric_form_value('id_adh', '');
$contribution['trans_id'] = get_numeric_form_value('trans_id', '');
$adh_selected = isset($contribution['id_adh']);
$tpl->assign('adh_selected', $adh_selected);

$type_selected = $contribution['id_cotis']!='' || get_form_value('type_selected', 0);
$tpl->assign('type_selected', $type_selected);

$cotis_extension = 0;
if ( isset($contribution['id_type_cotis']) ) {
    $request = 'SELECT cotis_extension FROM ' . PREFIX_DB .
        'types_cotisation WHERE id_type_cotis = ' . $contribution['id_type_cotis'];
    $cotis_extension = $DB->GetOne($request);
}

// initialize warning
$error_detected = array();

// flagging required fields
$required = array(
    'montant_cotis'     => 1,
    'date_debut_cotis'  => 1,
    'date_fin_cotis'    => $cotis_extension,
    'id_type_cotis'     => 1,
    'id_adh'            => 1
);

// Validation
$contribution['dyn'] = array();
if ( isset($_POST['valid']) ) {
    $contribution['dyn'] = extract_posted_dynamic_fields($DB, $_POST, array());

    $update_string = '';
    $insert_string_fields = '';
    $insert_string_values = '';

    // checking posted values
    $fields = &$DB->MetaColumns(PREFIX_DB . 'cotisations');
    while ( list($key, $properties) = each($fields) ) {
        $key = strtolower($key);
        if ( isset($_POST[$key]) ) {
            $value = trim($_POST[$key]);
        } else if ( $key == 'date_enreg' ) {
            $value = $DB->DBDate(time());
        } else if ($key == 'date_fin_cotis' && isset($_POST['duree_mois_cotis'])
            && isset($_POST['date_debut_cotis'])
        ) {
            $nmonths = trim($_POST['duree_mois_cotis']);
            if ( !is_numeric($nmonths) && $nmonths >= 0 ) {
                $error_detected[] = _T("- The duration must be an integer!");
                // To avoid error msg about date format
                $value='01/01/0001';
            } else if ( preg_match('@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@', $_POST['date_debut_cotis'], $debut) ) {
                $value = date(
                    'd/m/Y',
                    mktime(0, 0, 0, $debut[2] + $nmonths, $debut[1], $debut[3])
                );
            }
        } else {
            $value = '';
        }

        // fill up the contribution structure
        $contribution[$key] = stripslashes($value);

        // now, check validity
        if ( $value != '' ) {
            switch ($key) {
            case 'id_adh':
                if ( !is_numeric($value) ) {
                    $error_detected[] = _T("- Select a valid member name!");
                }
                break;
            // date
            case 'date_debut_cotis':
            case 'date_fin_cotis':
                if (preg_match('@^[0-9]{2}/[0-9]{2}/[0-9]{4}$@', $value, $array_jours)) {
                    $value = date_text2db($DB, $value);
                    if ( $value == '') {
                        $error_detected[] = _T("- Non valid date!") . ' (' . $key . ')';
                    }
                } else {
                    $error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!") . ' (' . $key . ')';
                }
                break;
            case 'montant_cotis':
                $us_value = strtr($value, ',', '.');
                if ( !is_numeric($us_value) ) {
                    $error_detected[] = _T("- The amount must be an integer!");
                }
                break;
            }
        }

        // dates already quoted
        if ( strncmp($key, 'date_', 5) != 0 ) {
            $value = $DB->qstr($value, get_magic_quotes_gpc());
        }
        /*FIXME : $trans_id undefined*/
        if (($key != 'date_fin_cotis' || $cotis_extension)
            && ($key != 'trans_id' || (isset($trans_id) && is_numeric($trans_id)))
        ) {
            $update_string .= ', ' . $key . '=' . $value;
            if ( $key != 'id_cotis' ) {
                $insert_string_fields .= ', ' . $key;
                $insert_string_values .= ', ' . $value;
            }
        }
    }

    // missing required fields?
    while ( list($key,$val) = each($required) ) {
        if ($val == 0) {
            continue;
        }
        if ( !isset($contribution[$key]) && !isset($disabled[$key]) ) {
            $error_detected[] = _T("- Mandatory field empty.")." ($key)";
        } elseif ( isset($contribution[$key]) && !isset($disabled[$key]) ) {
            if ( trim($contribution[$key])=='' ) {
                $error_detected[] = _T("- Mandatory field empty.")." ($key)";
            }
        }
    }

    $missing_amount = 0;
    if (count($error_detected) == 0) {
        // missing relations
        // Check that membership fees does not overlap
        if ($cotis_extension) {
            $table_cotis = PREFIX_DB."cotisations";
            $table_type_cotis = PREFIX_DB."types_cotisation";
            $id_adh = $contribution['id_adh'];
            $date_debut = date_text2db($DB, $contribution['date_debut_cotis']);
            $date_fin = date_text2db($DB, $contribution['date_fin_cotis']);
            $requete = "SELECT date_debut_cotis, date_fin_cotis FROM $table_cotis, $table_type_cotis WHERE $table_cotis.id_adh = $id_adh AND $table_cotis.id_type_cotis = $table_type_cotis.id_type_cotis AND cotis_extension = '1' ";
            if ( $contribution['id_cotis'] != '' ) {
                $requete .= "AND id_cotis != ".$contribution["id_cotis"]." ";
            }
            $requete .= "AND ((date_debut_cotis >= ".$date_debut." AND date_debut_cotis < ".$date_fin.") OR (date_fin_cotis > ".$date_debut." AND date_fin_cotis <= ".$date_fin."))";
            $result = $DB->Execute($requete);
            if ( !$result ) {
                print $requete . ': ' . $DB->ErrorMsg();
            }
            if ( !$result->EOF ) {
                $error_detected[] = _T("- Membership period overlaps period starting at ") . date_db2text($result->fields['date_debut_cotis']);
            }
            $result->Close();
        }

        // If there is a transaction for this contribution,
        //check that the sum of all contributioncoming from that transaction
        //doesn't exceed the transaction amount itself.

        if ( count($error_detected) == 0 && $contribution['trans_id'] ) {
            $missing_amount = missingContribAmount(
                $DB,
                $contribution['trans_id'],
                $error_detected
            );
            if ( $missing_amount < $contribution['montant_cotis'] ) {
                $error_detected[] = _T("-  Sum of all contributions exceed corresponding transaction amount.");
            } else {
                $missing_amount -= $contribution['montant_cotis'];
            }
        }
    }

    if ( count($error_detected) == 0 ) {
        if ($contribution['id_cotis'] == '' ) {
            $requete = 'INSERT INTO ' . PREFIX_DB . 'cotisations (' .
                substr($insert_string_fields, 1) . ') VALUES (' .
                substr($insert_string_values, 1) . ')';
            if (db_execute($DB, $requete, $error_detected)) {
                $contribution['id_cotis'] = get_last_auto_increment(
                    $DB,
                    PREFIX_DB . 'cotisations',
                    'id_cotis'
                );
                // logging
                $hist->add(_T("Contribution added"), '', $requete);
            }
        } else {
            $requete = 'UPDATE ' . PREFIX_DB . 'cotisations SET ' .
                substr($update_string, 1) . ' WHERE id_cotis=' .
                $contribution['id_cotis'];
            if (db_execute($DB, $requete, $error_detected)) {
                // logging
                $hist->add(_T("Contribution updated"), '', $requete);
            }
        }

        // dynamic fields
        set_all_dynamic_fields(
            $DB,
            'contrib',
            $contribution['id_cotis'],
            $contribution['dyn']
        );

        // update deadline
        if ( $cotis_extension ) {
            $date_fin = get_echeance($DB, $contribution['id_adh']);
            if ( $date_fin != '' ) {
                $date_fin_update = date_text2db($DB, implode("/", $date_fin));
            } else {
                $date_fin_update = "NULL";
            }
            $requete = "UPDATE " . PREFIX_DB . "adherents SET date_echeance=" .
                $date_fin_update . " WHERE id_adh=" . $contribution['id_adh'];
            $DB->Execute($requete);
        }
        if ( isset($_POST['mail_confirm']) && $_POST['mail_confirm'] == '1' ) {
            // Get member informations
            $requete = 'SELECT nom_adh, prenom_adh, email_adh, date_echeance, pref_lang FROM ' . PREFIX_DB . 'adherents WHERE id_adh =\'' . $contribution['id_adh'] . '\'';
            $result = &$DB->Execute($requete);
            if (!$result->EOF) {
                $contribution['nom_adh'] = $result->fields[0];
                $contribution['prenom_adh'] = $result->fields[1];
                $contribution['email_adh'] = $result->fields[2];
                $contribution['date_echeance'] = date_db2text($result->fields[3]);
                $contribution['pref_lang'] = $result->fields[4];
            }
            $result->Close();
            if ( $contribution['email_adh'] != '' ) {
                $texts = new texts();
                $mtxt = $texts->getTexts('contrib', $contribution['pref_lang']);
                $mtxt['tbody'] = str_replace(
                    '{NAME}',
                    $preferences->pref_nom,
                    $mtxt['tbody']
                );
                $mtxt['tbody'] = str_replace(
                    '{DEADLINE}',
                    custom_html_entity_decode($contribution['date_echeance']),
                    $mtxt['tbody']
                );
                $mtxt['tbody'] = str_replace(
                    '{COMMENT}',
                    custom_html_entity_decode($contribution['info_cotis']),
                    $mtxt['tbody']
                );
                $mail_result = custom_mail(
                    $contribution['email_adh'],
                    $mtxt['tsubject'],
                    $mtxt['tbody']
                );
            } else {
                $hist->add("A problem happened while sending contribution receipt to user:"." \"" . $contribution['prenom_adh']." ".$contribution['nom_adh']."<".$contribution['email_adh'] . ">\"");
                $error_detected[] = _T("A problem happened while sending contribution receipt to user:")." \"" . $contribution['prenom_adh']." ".$contribution['nom_adh']."<".$contribution['email_adh'] . ">\"";
            }
            // Sent email to admin if pref checked
            if ( $preferences->pref_bool_mailadh ) {
                // Get email text in database
                $texts = new texts();
                $mtxt = $texts->getTexts("newcont", $preferences->pref_lang);
                $mtxt['tsubject'] = str_replace(
                    '{NAME_ADH}',
                    custom_html_entity_decode($contribution['nom_adh']),
                    $mtxt['tsubject']
                );
                $mtxt['tsubject'] = str_replace(
                    '{SURNAME_ADH}',
                    custom_html_entity_decode($contribution['prenom_adh']),
                    $mtxt['tsubject']
                );
                $mtxt['tbody'] = str_replace(
                    '{NAME_ADH}',
                    custom_html_entity_decode($contribution['nom_adh']),
                    $mtxt['tbody']
                );
                $mtxt['tbody'] = str_replace(
                    '{SURNAME_ADH}',
                    custom_html_entity_decode($contribution['prenom_adh']),
                    $mtxt['tbody']
                );
                $mtxt['tbody'] = str_replace(
                    '{DEADLINE}',
                    custom_html_entity_decode($contribution['date_echeance']),
                    $mtxt['tbody']
                );
                $mtxt['tbody'] = str_replace(
                    '{COMMENT}',
                    custom_html_entity_decode($contribution['info_cotis']),
                    $mtxt['tbody']
                );
                $mail_result = custom_mail(
                    $preferences->pref_email_newadh,
                    $mtxt['tsubject'],
                    $mtxt['tbody']
                );
                if ( $mail_result != 1 ) {
                    $hist->add(_T("A problem happened while sending email to admin for user:")." \"" . $contribution['prenom_adh']." ".$contribution['nom_adh']."<".$contribution['email_adh'] . ">\"");
                    $error_detected[] = _T("A problem happened while sending email to admin for user:")." \"" . $contribution['prenom_adh']." ".$contribution['nom_adh']."<".$contribution['email_adh'] . ">\"";
                }
            }
        }

        if ( count($error_detected) == 0 ) {
            if ( $missing_amount > 0 ) {
                $url = 'ajouter_contribution.php?trans_id=' .
                    $contribution['trans_id'] . '&id_adh=' .
                    $contribution['id_adh'];
            } else {
                $url = 'gestion_contributions.php?id_adh=' . $contribution['id_adh'];
            }
            header('location: ' . $url);
        }
    }

    if ( !isset($contribution['duree_mois_cotis'])
        || $contribution['duree_mois_cotis'] == ''
    ) {
        // On error restore entered value or default to display the form again
        if ( isset($_POST['duree_mois_cotis'])
            && $_POST['duree_mois_cotis'] != ''
        ) {
            $contribution['duree_mois_cotis'] = $_POST['duree_mois_cotis'];
        } else {
            $contribution['duree_mois_cotis'] = $preferences->pref_membership_ext;
        }
    }
} else {
    if ( $contribution['id_cotis'] == '') {
        // initialiser la structure contribution à vide (nouvelle contribution)
        $contribution['duree_mois_cotis'] = $preferences->pref_membership_ext;
        if ( $cotis_extension && isset($contribution['id_adh']) ) {
            $curend = get_echeance($DB, $contribution['id_adh']);
            if ($curend == '') {
                $beg_cotis = time();
            } else {
                $beg_cotis = mktime(0, 0, 0, $curend[1], $curend[0], $curend[2]);
                if ( $beg_cotis < time() ) {
                    // Member didn't renew on time
                    $beg_cotis = time();
                }
            }
        } else {
            $beg_cotis = time();
        }
        $contribution['date_debut_cotis'] = date('d/m/Y', $beg_cotis);
        // End date is the date of next period after this one
        $contribution['date_fin_cotis'] = beg_membership_after($beg_cotis);
        if ( is_numeric($contribution['trans_id']) ) {
            $contribution['montant_cotis'] = missingContribAmount(
                $DB,
                $contribution['trans_id'],
                $error_detected
            );
        }
    } else {
        // initialize coontribution structure with database values
        $sql =  'SELECT * FROM ' . PREFIX_DB . 'cotisations WHERE id_cotis=' .
            $contribution['id_cotis'];
        $result = &$DB->Execute($sql);
        if ( $result->EOF ) {
            header('location: index.php');
        } else {
            // plain info
            $contribution = $result->fields;

            // reformat dates
            $contribution['date_debut_cotis'] = date_db2text(
                $contribution['date_debut_cotis']
            );
            $contribution['date_fin_cotis'] = date_db2text(
                $contribution['date_fin_cotis']
            );
            $contribution['duree_mois_cotis'] = distance_months(
                $contribution['date_debut_cotis'],
                $contribution['date_fin_cotis']
            );
            $request = 'SELECT cotis_extension FROM ' . PREFIX_DB .
                'types_cotisation WHERE id_type_cotis = ' .
                $contribution['id_type_cotis'];
            $cotis_extension = &$DB->GetOne($request);
        }

        // dynamic fields
        $contribution['dyn'] = get_dynamic_fields(
            $DB,
            'contrib',
            $contribution["id_cotis"],
            false
        );
    }
}

// template variable declaration
$tpl->assign('required', $required);
$tpl->assign('data', $contribution);
$tpl->assign('error_detected', $error_detected);

// contribution types
$requete = 'SELECT DISTINCT cotis_extension FROM ' . PREFIX_DB .
    'types_cotisation';
$exval = $DB->GetOne($requete);
$requete = 'SELECT id_type_cotis, libelle_type_cotis FROM ' . PREFIX_DB .
    'types_cotisation ';
if ( $type_selected == 1 ) {
    $requete .= 'WHERE cotis_extension=' . ($cotis_extension ? '1' : '0');
}
$requete .= ' ORDER BY libelle_type_cotis';
$result = &$DB->Execute($requete);
if (!$result) {
    print $DB->ErrorMsg();
}
while ( !$result->EOF ) {
    $type_cotis_options[$result->fields[0]] = stripslashes(_T($result->fields[1]));
    $result->MoveNext();
}
$result->Close();
$tpl->assign('type_cotis_options', $type_cotis_options);

// members
$requete = 'SELECT id_adh, nom_adh, prenom_adh FROM ' . PREFIX_DB .
    'adherents ORDER BY nom_adh, prenom_adh';
$result = &$DB->Execute($requete);
if ( $result->EOF ) {
    $adh_options = array('' => _T("You must first register a member"));
} else {
    while ( !$result->EOF ) {
        $adh_options[$result->fields[0]] = stripslashes(
            strtoupper($result->fields[1]) . ' ' . $result->fields[2]
        );
        $result->MoveNext();
    }
}
$result->Close();
$tpl->assign('adh_options', $adh_options);
$tpl->assign('require_calendar', true);

$tpl->assign('pref_membership_ext', $cotis_extension ? $preferences->pref_membership_ext : '');
$tpl->assign('cotis_extension', $cotis_extension);

// - declare dynamic fields for display
$dynamic_fields = prepare_dynamic_fields_for_display(
    $DB,
    'contrib',
    $contribution['dyn'],
    array(),
    1
);
$tpl->assign('dynamic_fields', $dynamic_fields);

// page generation
$content = $tpl->fetch('ajouter_contribution.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
