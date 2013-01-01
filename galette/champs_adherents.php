<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Relationship between members table fields and their translations
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-09-02
 */

$adh_fields = array(
    "id_adh"            =>  _T("Identifiant:"),
    "id_statut"         =>  _T("Status:"),
    "nom_adh"           =>  _T("Name:"),
    "prenom_adh"        =>  _T("First name:"),
    "pseudo_adh"        =>  _T("Nickname:"),
    "titre_adh"         =>  _T("Title:"),
    "ddn_adh"           =>  _T("Birth date:"),
    "adresse_adh"       =>  _T("Address:"),
    "adresse2_adh"      =>  _T("Address (continuation):"),
    "cp_adh"            =>  _T("Zip Code:"),
    "ville_adh"         =>  _T("City:"),
    "pays_adh"          =>  _T("Country:"),
    "tel_adh"           =>  _T("Phone:"),
    "gsm_adh"           =>  _T("Mobile phone:"),
    "email_adh"         =>  _T("E-Mail:"),
    "url_adh"           =>  _T("Website:"),
    "icq_adh"           =>  _T("ICQ:"),
    "msn_adh"           =>  _T("MSN:"),
    "jabber_adh"        =>  _T("Jabber:"),
    "info_adh"          =>  _T("Other informations (admin):"),
    "info_public_adh"   =>  _T("Other informations:"),
    "prof_adh"          =>  _T("Profession:"),
    "login_adh"         =>  _T("Username:"),
    "mdp_adh"           =>  _T("Password:"),
    "date_crea_adh"     =>  _T("Creation date:"),
    "activite_adh"      =>  _T("Account:"),
    "bool_admin_adh"    =>  _T("Galette Admin:"),
    "bool_exempt_adh"   =>  _T("Freed of dues:"),
    "bool_display_info" =>  _T("Be visible in the members list:"),
    "date_echeance"     =>  _T("Due date:"),
    "pref_lang"         =>  _T("Language:"),
    "lieu_naissance"    =>  _T("Birthplace:"),
    "gpgid"             =>  _T("Id GNUpg (GPG):"),
    "fingerprint"       =>  _T("fingerprint:")
);
