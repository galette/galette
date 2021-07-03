-- Update fields length
ALTER TABLE galette_adherents CHANGE nom_adh nom_adh varchar(255) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE prenom_adh prenom_adh varchar(255) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE pseudo_adh pseudo_adh varchar(255) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE adresse_adh adresse_adh text NOT NULL;
ALTER TABLE galette_adherents CHANGE ville_adh ville_adh varchar(200) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE pays_adh pays_adh varchar(200) default NULL;
ALTER TABLE galette_adherents CHANGE tel_adh tel_adh varchar(50) default NULL;
ALTER TABLE galette_adherents CHANGE gsm_adh gsm_adh varchar(50) default NULL;
ALTER TABLE galette_adherents CHANGE url_adh url_adh varchar(255) default NULL;
ALTER TABLE galette_adherents CHANGE login_adh login_adh varchar(255) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE mdp_adh mdp_adh varchar(255) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE fingerprint fingerprint varchar(255) NOT NULL default '';

ALTER TABLE galette_transactions CHANGE trans_desc trans_desc varchar(255) NOT NULL default '';

ALTER TABLE galette_statuts CHANGE libelle_statut libelle_statut varchar(255) NOT NULL default '';

ALTER TABLE galette_titles CHANGE long_label long_label varchar(100) default '';

ALTER TABLE galette_preferences CHANGE val_pref val_pref varchar(255) NOT NULL default '';

ALTER TABLE galette_logs CHANGE adh_log adh_log varchar(255) NOT NULL default '';

ALTER TABLE galette_field_types CHANGE field_name field_name varchar(255) NOT NULL default '';

ALTER TABLE galette_l10n CHANGE text_orig text_orig varchar(255) NOT NULL;
ALTER TABLE galette_l10n CHANGE text_trans text_trans varchar(255) NOT NULL;

ALTER TABLE galette_tmppasswds CHANGE tmp_passwd tmp_passwd varchar(250) NOT NULL;

ALTER TABLE galette_texts CHANGE tcomment tcomment varchar(255) NOT NULL;

ALTER TABLE galette_fields_categories CHANGE category category varchar(100) NOT NULL default '';

ALTER TABLE galette_mailing_history CHANGE mailing_sender_name mailing_sender_name varchar(255) DEFAULT NULL;

ALTER TABLE galette_groups CHANGE group_name group_name varchar(250) NOT NULL;

ALTER TABLE galette_pdfmodels CHANGE model_title model_title varchar(250);
ALTER TABLE galette_pdfmodels CHANGE model_subtitle model_subtitle varchar(250);

ALTER TABLE galette_tmplinks CHANGE hash hash varchar(250) NOT NULL;

DROP TABLE IF EXISTS galette_required;

UPDATE galette_database SET version = 0.950;
