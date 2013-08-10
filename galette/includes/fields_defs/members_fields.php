<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Member's table fields declarations
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.7.2.8 - 2012-12-18
 */

use Galette\Entity\FieldsConfig as FieldsConfig;
use Galette\Entity\FieldsCategories as FieldsCategories;

$members_fields = array(
    'id_adh' => array(
        'label'    => _T("Identifiant:"),
        'propname' => 'id',
        'required' => false,
        'visible'  => FieldsConfig::HIDDEN,
        'position' => 0,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'id_statut' => array(
        'label'    => _T("Status:"),
        'propname' => 'status',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 1,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'nom_adh' => array(
        'label'    => _T("Name:"),
        'propname' => 'name',
        'required' => true ,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 2,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'prenom_adh' => array(
        'label'    => _T("First name:"),
        'propname' => 'surname',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 3,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'societe_adh' => array(
        'label'    => _T("Company:"),
        'propname' => 'company_name',
        'required' => false ,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 4,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'pseudo_adh' => array(
        'label'    => _T("Nickname:"),
        'propname' => 'nickname',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 5,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'titre_adh' => array(
        'label'    => _T("Title:"),
        'propname' => 'title',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 6,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'ddn_adh' => array(
        'label'    => _T("Birth date:"),
        'propname' => 'birthdate',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 7,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'sexe_adh' => array(
        'label'    => _T("Gender:"),
        'propname' => 'gender',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 8,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'adresse_adh' => array(
        'label'    => _T("Address:"),
        'propname' => 'adress',
        'required' => true,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 9,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    /** TODO remove second adress... */
    'adresse2_adh' => array(
        'label'    => _T("Address (continuation)"),
        'propname' => 'adress_continuation',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 10,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'cp_adh' => array(
        'label'    => _T("Zip Code:"),
        'propname' => 'zipcode',
        'required' => true,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 11,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'ville_adh' => array(
        'label'    => _T("City:"),
        'propname' => 'town',
        'required' => true,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 12,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'pays_adh' => array(
        'label'    => _T("Country:"),
        'propname' => 'country',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 13,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'tel_adh' => array(
        'label'    => _T("Phone:"),
        'propname' => 'phone',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 14,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'gsm_adh' => array(
        'label'    => _T("Mobile phone:"),
        'propname' => 'gsm',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 15,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'email_adh' => array(
        'label'    => _T("E-Mail:"),
        'propname' => 'email',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 16,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'url_adh' => array(
        'label'    => _T("Website:"),
        'propname' => 'website',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 17,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'icq_adh' => array(
        'label'    => _T("ICQ:"),
        'propname' => 'icq',
        'required' => false,
        'visible'  => FieldsConfig::HIDDEN,
        'position' => 18,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'msn_adh' => array(
        'label'    => _T("MSN:"),
        'propname' => 'msn',
        'required' => false,
        'visible'  => FieldsConfig::HIDDEN,
        'position' => 19,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'jabber_adh' => array(
        'label'    => _T("Jabber:"),
        'propname' => 'jabber',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 20,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'info_adh' => array(
        'label'    => _T("Other informations (admin):"),
        'propname' => 'other_infos_admin',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 21,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'info_public_adh' => array(
        'label'    => _T("Other informations:"),
        'propname' => 'others_infos',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 22,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'prof_adh' => array(
        'label'    => _T("Profession:"),
        'propname' => 'job',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 23,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'login_adh' => array(
        'label'    => _T("Username:"),
        'propname' => 'login',
        'required' => true,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 24,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'mdp_adh' => array(
        'label'    => _T("Password:"),
        'propname' => 'password',
        'required' => true,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 25,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'date_crea_adh' => array(
        'label'    => _T("Creation date:"),
        'propname' => 'creation_date',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 26,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'date_modif_adh' => array(
        'label'    => _T("Modification date:"),
        'propname' => 'modification_date',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 27,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'activite_adh' => array(
        'label'    => _T("Account:"),
        'propname' => 'active',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 28,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'bool_admin_adh' => array(
        'label'    => _T("Galette Admin:"),
        'propname' => 'admin',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 29,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'bool_exempt_adh' => array(
        'label'    => _T("Freed of dues:"),
        'propname' => 'due_free',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 30,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'bool_display_info' => array(
        'label'    => _T("Be visible in the members list:"),
        'propname' => 'appears_in_list',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 31,
        'category' => FieldsCategories::ADH_CATEGORY_GALETTE
    ),
    'date_echeance' => array(
        'label'    => _T("Due date:"),
        'propname' => 'due_date',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 32,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'pref_lang' => array(
        'label'    => _T("Language:"),
        'propname' => 'language',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 33,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'lieu_naissance' => array(
        'label'    => _T("Birthplace:"),
        'propname' => 'birth_place',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 34,
        'category' => FieldsCategories::ADH_CATEGORY_IDENTITY
    ),
    'gpgid' => array(
        'label'    => _T("Id GNUpg (GPG):"),
        'propname' => 'gnupgid',
        'required' => false,
        'visible'  => FieldsConfig::VISIBLE,
        'position' => 35,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    ),
    'fingerprint' => array(
        'label'    => _T("fingerprint:"),
        'propname' => 'fingerprint',
        'required' => false,
        'visible'  => FieldsConfig::HIDDEN,
        'position' => 36,
        'category' => FieldsCategories::ADH_CATEGORY_CONTACT
    )
);

