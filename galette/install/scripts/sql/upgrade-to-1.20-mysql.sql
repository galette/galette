SET FOREIGN_KEY_CHECKS = 0;

-- Remove size on integers
ALTER TABLE galette_adherents CHANGE id_adh id_adh INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_adherents CHANGE id_statut id_statut INT UNSIGNED NOT NULL; -- also remove default value here
ALTER TABLE galette_adherents CHANGE titre_adh titre_adh INT UNSIGNED DEFAULT NULL;
ALTER TABLE galette_adherents CHANGE parent_id parent_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE galette_adherents CHANGE sexe_adh sexe_adh SMALLINT NOT NULL;
ALTER TABLE galette_adherents CHANGE pref_lang pref_lang varchar(20);
ALTER TABLE galette_adherents CHANGE ddn_adh ddn_adh DATE;
ALTER TABLE galette_adherents CHANGE date_crea_adh date_crea_adh DATE NOT NULL;
ALTER TABLE galette_adherents CHANGE date_modif_adh date_modif_adh DATE NOT NULL;
ALTER TABLE galette_adherents CHANGE login_adh login_adh VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE galette_adherents CHANGE adresse_adh adresse_adh LONGTEXT NOT NULL;
ALTER TABLE galette_adherents CHANGE info_adh info_adh LONGTEXT;
ALTER TABLE galette_adherents CHANGE info_public_adh info_public_adh LONGTEXT;
ALTER TABLE galette_adherents CHANGE lieu_naissance lieu_naissance VARCHAR(255);
ALTER TABLE galette_adherents CHANGE gpgid gpgid LONGTEXT;
ALTER TABLE galette_adherents CHANGE fingerprint fingerprint VARCHAR(255) DEFAULT NULL;

ALTER TABLE galette_cotisations CHANGE id_cotis id_cotis INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE galette_cotisations CHANGE id_adh id_adh INT UNSIGNED NOT NULL; -- also remove default value here
ALTER TABLE galette_cotisations CHANGE id_type_cotis id_type_cotis INT UNSIGNED NOT NULL; -- also remove default value here
ALTER TABLE galette_cotisations CHANGE type_paiement_cotis type_paiement_cotis INT UNSIGNED NOT NULL; -- also remove default value here
ALTER TABLE galette_cotisations CHANGE trans_id trans_id INT UNSIGNED NOT NULL;
ALTER TABLE galette_cotisations CHANGE date_enreg date_enreg DATE NOT NULL;
ALTER TABLE galette_cotisations CHANGE date_debut_cotis date_debut_cotis DATE NOT NULL;
ALTER TABLE galette_cotisations CHANGE date_fin_cotis date_fin_cotis DATE;
ALTER TABLE galette_cotisations CHANGE info_cotis info_cotis LONGTEXT;

ALTER TABLE galette_transactions CHANGE trans_id trans_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_transactions CHANGE id_adh id_adh INT UNSIGNED DEFAULT NULL;
ALTER TABLE galette_transactions CHANGE type_paiement_trans type_paiement_trans INT UNSIGNED DEFAULT NULL;
ALTER TABLE galette_transactions CHANGE trans_date trans_date DATE NOT NULL;

ALTER TABLE galette_statuts CHANGE id_statut id_statut INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_titles CHANGE id_title id_title INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_types_cotisation CHANGE id_type_cotis id_type_cotis INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_preferences CHANGE id_pref id_pref INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_logs CHANGE id_log id_log INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_logs CHANGE text_log text_log LONGTEXT;
ALTER TABLE galette_logs CHANGE action_log action_log LONGTEXT;
ALTER TABLE galette_logs CHANGE sql_log sql_log LONGTEXT;

ALTER TABLE galette_field_types CHANGE field_id field_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_field_types CHANGE field_index field_index INT NOT NULL DEFAULT 0;
ALTER TABLE galette_field_types CHANGE field_perm field_perm INT NOT NULL DEFAULT 1;
ALTER TABLE galette_field_types CHANGE field_type field_type INT NOT NULL DEFAULT 0;
ALTER TABLE galette_field_types CHANGE field_pos field_pos INT NOT NULL DEFAULT 0;
ALTER TABLE galette_field_types CHANGE field_width field_width INT DEFAULT NULL;
ALTER TABLE galette_field_types CHANGE field_height field_height INT DEFAULT NULL;
ALTER TABLE galette_field_types CHANGE field_min_size field_min_size INT DEFAULT NULL;
ALTER TABLE galette_field_types CHANGE field_size field_size INT DEFAULT NULL;
ALTER TABLE galette_field_types CHANGE field_repeat field_repeat INT DEFAULT NULL;
ALTER TABLE galette_field_types CHANGE field_information field_information LONGTEXT;

ALTER TABLE galette_dynamic_fields CHANGE item_id item_id INT NOT NULL DEFAULT 0;
ALTER TABLE galette_dynamic_fields CHANGE field_id field_id INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE galette_dynamic_fields CHANGE val_index val_index INT NOT NULL DEFAULT 0;
ALTER TABLE galette_dynamic_fields CHANGE field_val field_val LONGTEXT;

ALTER TABLE galette_pictures CHANGE id_adh id_adh INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE galette_l10n CHANGE text_nref text_nref INT NOT NULL DEFAULT 1;
ALTER TABLE galette_l10n CHANGE text_trans text_trans varchar(255) NOT NULL DEFAULT '';
ALTER TABLE galette_l10n ADD text_orig_sum varchar(40) NOT NULL;
UPDATE galette_l10n SET text_orig_sum = MD5(text_orig);
ALTER TABLE galette_l10n DROP PRIMARY KEY, ADD PRIMARY KEY (text_orig_sum, text_locale);

ALTER TABLE galette_tmppasswds CHANGE id_adh id_adh INT UNSIGNED NOT NULL;

ALTER TABLE galette_mailing_history CHANGE mailing_id mailing_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_mailing_history CHANGE mailing_sender mailing_sender INT UNSIGNED;
ALTER TABLE galette_mailing_history CHANGE mailing_body mailing_body LONGTEXT NOT NULL;
ALTER TABLE galette_mailing_history CHANGE mailing_recipients mailing_recipients LONGTEXT NOT NULL;

ALTER TABLE galette_groups CHANGE id_group id_group INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_groups CHANGE parent_group parent_group INT UNSIGNED DEFAULT NULL;

ALTER TABLE galette_groups_managers CHANGE id_group id_group INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_groups_managers CHANGE id_adh id_adh INT UNSIGNED NOT NULL;

ALTER TABLE galette_groups_members CHANGE id_group id_group INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_groups_members CHANGE id_adh id_adh INT UNSIGNED NOT NULL;

ALTER TABLE galette_reminders CHANGE reminder_id reminder_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_reminders CHANGE reminder_type reminder_type INT NOT NULL;
ALTER TABLE galette_reminders CHANGE reminder_dest reminder_dest INT unsigned;
ALTER TABLE galette_reminders CHANGE reminder_comment reminder_comment LONGTEXT;

ALTER TABLE galette_pdfmodels CHANGE model_id model_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_pdfmodels CHANGE model_parent model_parent INT UNSIGNED DEFAULT NULL;
ALTER TABLE galette_pdfmodels CHANGE model_type model_type SMALLINT NOT NULL;
ALTER TABLE galette_pdfmodels CHANGE model_header model_header LONGTEXT;
ALTER TABLE galette_pdfmodels CHANGE model_footer model_footer LONGTEXT;
ALTER TABLE galette_pdfmodels CHANGE model_body model_body LONGTEXT;
ALTER TABLE galette_pdfmodels CHANGE model_styles model_styles LONGTEXT;

ALTER TABLE galette_import_model CHANGE model_id model_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_import_model CHANGE model_fields model_fields LONGTEXT;

ALTER TABLE galette_paymenttypes CHANGE type_id type_id INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE galette_searches CHANGE search_id search_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_searches CHANGE id_adh id_adh INT UNSIGNED;
ALTER TABLE galette_searches CHANGE parameters parameters LONGTEXT NOT NULL;

ALTER TABLE galette_tmplinks CHANGE id id INT UNSIGNED;

ALTER TABLE galette_socials CHANGE id_social id_social INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_socials CHANGE id_adh id_adh INT UNSIGNED DEFAULT NULL;

ALTER TABLE galette_documents CHANGE id_document id_document INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_documents CHANGE visible visible smallint NOT NULL;
ALTER TABLE galette_documents CHANGE comment comment LONGTEXT;

ALTER TABLE galette_payments_schedules CHANGE id_schedule id_schedule INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_payments_schedules CHANGE id_cotis id_cotis INT UNSIGNED NOT NULL;
ALTER TABLE galette_payments_schedules CHANGE id_paymenttype id_paymenttype INT UNSIGNED NOT NULL;
ALTER TABLE galette_payments_schedules CHANGE comment comment LONGTEXT;

ALTER TABLE galette_texts CHANGE tid tid INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_texts CHANGE tbody tbody LONGTEXT NOT NULL;

ALTER TABLE galette_fields_categories CHANGE id_field_category id_field_category INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE galette_fields_categories CHANGE position position SMALLINT NOT NULL;

ALTER TABLE galette_fields_config CHANGE position position SMALLINT NOT NULL;
ALTER TABLE galette_fields_config CHANGE id_field_category id_field_category INT UNSIGNED NOT NULL;
ALTER TABLE galette_fields_config CHANGE list_position list_position SMALLINT NOT NULL;
ALTER TABLE galette_fields_config CHANGE width_in_forms width_in_forms SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE galette_fields_config CHANGE list_visible list_visible TINYINT(1) NOT NULL;

ALTER TABLE galette_statuts CHANGE priorite_statut priorite_statut SMALLINT NOT NULL default 0;

ALTER TABLE galette_field_types CHANGE field_width_in_forms field_width_in_forms SMALLINT NOT NULL default 1;

ALTER TABLE galette_types_cotisation CHANGE cotis_extension cotis_extension SMALLINT NOT NULL default 0;
ALTER TABLE galette_types_cotisation CHANGE libelle_type_cotis libelle_type_cotis VARCHAR(255) NOT NULL default '';

ALTER TABLE galette_pictures CHANGE format format VARCHAR(30) NOT NULL default '';

-- adherents <=> status relation
ALTER TABLE galette_adherents DROP CONSTRAINT galette_adherents_ibfk_1;
ALTER TABLE galette_adherents ADD FOREIGN KEY galette_adherents_ibfk_1 (id_statut) REFERENCES galette_statuts (id_statut) ON DELETE RESTRICT ON UPDATE CASCADE;
-- adherents <=> titles relation
ALTER TABLE galette_adherents DROP CONSTRAINT galette_adherents_ibfk_2;
ALTER TABLE galette_adherents ADD FOREIGN KEY galette_adherents_ibfk_2 (titre_adh) REFERENCES galette_titles (id_title) ON DELETE RESTRICT ON UPDATE CASCADE;
-- adherents <=> parents relation
ALTER TABLE galette_adherents DROP CONSTRAINT galette_adherents_ibfk_3;
ALTER TABLE galette_adherents ADD FOREIGN KEY galette_adherents_ibfk_3 (parent_id) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
-- contributions <=> types relation
ALTER TABLE galette_cotisations DROP CONSTRAINT galette_cotisations_ibfk_1;
ALTER TABLE galette_cotisations ADD FOREIGN KEY galette_cotisations_ibfk_1 (id_type_cotis) REFERENCES galette_types_cotisation (id_type_cotis) ON DELETE RESTRICT ON UPDATE CASCADE;
-- contributions <=> adherents relation
ALTER TABLE galette_cotisations DROP CONSTRAINT galette_cotisations_ibfk_2;
ALTER TABLE galette_cotisations ADD FOREIGN KEY galette_cotisations_ibfk_2 (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
-- contributions <=> transactions relation
ALTER TABLE galette_cotisations DROP CONSTRAINT galette_cotisations_ibfk_3;
ALTER TABLE galette_cotisations ADD FOREIGN KEY galette_cotisations_ibfk_3 (trans_id) REFERENCES galette_transactions (trans_id) ON DELETE RESTRICT ON UPDATE CASCADE;
-- contributions <=> payment types relation
ALTER TABLE galette_cotisations DROP CONSTRAINT galette_cotisations_ibfk_4;
ALTER TABLE galette_cotisations ADD FOREIGN KEY galette_cotisations_ibfk_4 (type_paiement_cotis) REFERENCES galette_paymenttypes (type_id) ON DELETE RESTRICT ON UPDATE CASCADE;
-- dynamic fields <=> fields types relation
ALTER TABLE galette_dynamic_fields DROP CONSTRAINT galette_dynamic_fields_ibfk_1;
ALTER TABLE galette_dynamic_fields ADD FOREIGN KEY galette_dynamic_fields_ibfk_1 (field_id) REFERENCES galette_field_types (field_id) ON DELETE RESTRICT ON UPDATE CASCADE;
-- fields config <=> categories relation
-- ALTER TABLE galette_fields_config DROP CONSTRAINT galette_fields_config_ibfk_1;
-- ALTER TABLE galette_fields_config ADD FOREIGN KEY galette_fields_config_ibfk_1 (id_field_category) REFERENCES galette_fields_categories (id_field_category) ON DELETE RESTRICT ON UPDATE CASCADE;
-- groups <=> parent relation
-- ALTER TABLE galette_groups DROP CONSTRAINT galette_groups_ibfk_1;
-- ALTER TABLE galette_groups ADD FOREIGN KEY galette_groups_ibfk_1 (parent_group) REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE;
-- groups managers <=> adherents relation
ALTER TABLE galette_groups_managers DROP CONSTRAINT galette_groups_managers_ibfk_1;
ALTER TABLE galette_groups_managers ADD FOREIGN KEY galette_groups_managers_ibfk_1 (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
-- groups managers <=> group relation
-- ALTER TABLE galette_groups_managers DROP CONSTRAINT galette_groups_managers_ibfk_2;
-- ALTER TABLE galette_groups_managers ADD FOREIGN KEY galette_groups_managers_ibfk_2 (id_group) REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE;
-- groups members <=> adherents relation
ALTER TABLE galette_groups_members DROP CONSTRAINT galette_groups_members_ibfk_1;
ALTER TABLE galette_groups_members ADD FOREIGN KEY galette_groups_members_ibfk_1 (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
-- groups members <=> group relation
-- ALTER TABLE galette_groups_members DROP CONSTRAINT galette_groups_members_ibfk_2;
-- ALTER TABLE galette_groups_members ADD FOREIGN KEY galette_groups_members_ibfk_2 (id_group) REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE;
-- mailing histories <=> senders relation
ALTER TABLE galette_mailing_history DROP CONSTRAINT galette_mailing_history_ibfk_1;
ALTER TABLE galette_mailing_history ADD FOREIGN KEY galette_mailing_history_ibfk_1 (mailing_sender) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
-- reminders <=> dest relation
ALTER TABLE galette_reminders DROP CONSTRAINT galette_reminders_ibfk_1;
ALTER TABLE galette_reminders ADD FOREIGN KEY galette_reminders_ibfk_1 (reminder_dest) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE;
-- temp passwords <=> adherents relation
ALTER TABLE galette_tmppasswds DROP CONSTRAINT galette_tmppasswds_ibfk_1;
ALTER TABLE galette_tmppasswds ADD FOREIGN KEY galette_tmppasswds_ibfk_1 (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE;
-- transactions <=> adherents relation
ALTER TABLE galette_transactions DROP CONSTRAINT galette_transactions_ibfk_1;
ALTER TABLE galette_transactions ADD FOREIGN KEY galette_transactions_ibfk_1 (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
-- transactions <=> payment types relation
ALTER TABLE galette_transactions DROP CONSTRAINT galette_transactions_ibfk_2;
ALTER TABLE galette_transactions ADD FOREIGN KEY galette_transactions_ibfk_2 (type_paiement_trans) REFERENCES galette_paymenttypes (type_id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE galette_cotisations ADD paid tinyint(1) DEFAULT FALSE;

SET FOREIGN_KEY_CHECKS = 1;
