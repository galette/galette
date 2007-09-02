<?php
//
// champs_adherents.php, 01 septembre 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	champs_adherents.class.php
// Author's email :     	johan@x-tnd.be
// Author's Website :   	http://galette.tuxfamily.org
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
/**
 * Relations entre les champs de la table des adhérents
 * et leur signification en texte complet.
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL License 2.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.63
 */

$adh_fields = array(
	"id_adh"	=>	_T("Identifiant:"),
	"id_statut"	=>	_T("Status:"),
	"nom_adh"	=>	_T("Name:"),
	"prenom_adh"	=>	_T("First name:"),
	"pseudo_adh"	=>	_T("Nickname:"),
	"titre_adh"	=>	_T("Title:"),
	"ddn_adh"	=>	_T("birth date:"),
	"adresse_adh"	=>	_T("Address:"),
// 	"adresse2_adh"	=>	_T(""),
	"cp_adh"	=>	_T("Zip Code:"),
	"ville_adh"	=>	_T("City:"),
	"pays_adh"	=>	_T("Country:"),
	"tel_adh"	=>	_T("Phone:"),
	"gsm_adh"	=>	_T("Mobile phone:"),
	"email_adh"	=>	_T("E-Mail:"),
	"url_adh"	=>	_T("Website:"),
	"icq_adh"	=>	_T("ICQ:"),
	"msn_adh"	=>	_T("MSN:"),
	"jabber_adh"	=>	_T("Jabber:"),
	"info_adh"	=>	_T("Other informations (admin):"),
	"info_public_adh"	=>	_T("Other informations:"),
	"prof_adh"	=>	_T("Profession:"),
	"login_adh"	=>	_T("Username:"),
	"mdp_adh"	=>	_T("Password:"),
	"date_crea_adh"	=>	_T("Creation date:"),
	"activite_adh"	=>	_T("Account:"),
	"bool_admin_adh"	=>	_T("Galette Admin:"),
	"bool_exempt_adh"	=>	_T("Freed of dues:"),
	"bool_display_info"	=>	_T("Be visible in the<br /> members list :"),
// 	"date_echeance"	=>	_T(""),
	"pref_lang"	=>	_T("Language:"),
// 	"lieu_naissance"	=>	_T(""),
	"gpgid"	=>	_T("Id GNUpg (GPG):"),
	"fingerprint"	=>	_T("fingerprint:")
);

?>