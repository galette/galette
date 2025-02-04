-- drop useless sequences, change columns to identity, fix nextval
DROP SEQUENCE IF EXISTS galette_adherents_id_seq;
DROP SEQUENCE IF EXISTS adherents_id_seq;
ALTER TABLE galette_adherents ALTER COLUMN id_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN id_adh ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_adherents', 'id_adh'), max(id_adh) FROM galette_adherents;

DROP SEQUENCE IF EXISTS galette_cotisations_id_seq;
DROP SEQUENCE IF EXISTS cotisations_id_seq;
ALTER TABLE galette_cotisations ALTER COLUMN id_cotis DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER COLUMN id_cotis ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_cotisations', 'id_cotis'), max(id_cotis) FROM galette_cotisations;

DROP SEQUENCE IF EXISTS galette_statuts_id_seq;
ALTER TABLE galette_statuts ALTER COLUMN id_statut DROP DEFAULT;
ALTER TABLE galette_statuts ALTER COLUMN id_statut ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_statuts', 'id_statut'), max(id_statut) FROM galette_statuts;

DROP SEQUENCE IF EXISTS galette_transactions_id_seq;
ALTER TABLE galette_transactions ALTER COLUMN trans_id DROP DEFAULT;
ALTER TABLE galette_transactions ALTER COLUMN trans_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_transactions', 'trans_id'), max(trans_id) FROM galette_transactions;

DROP SEQUENCE IF EXISTS galette_preferences_id_seq;
ALTER TABLE galette_preferences ALTER COLUMN id_pref DROP DEFAULT;
ALTER TABLE galette_preferences ALTER COLUMN id_pref ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_preferences', 'id_pref'), max(id_pref) FROM galette_preferences;

DROP SEQUENCE IF EXISTS galette_logs_id_seq;
DROP SEQUENCE IF EXISTS logs_id_seq;
ALTER TABLE galette_logs ALTER COLUMN id_log DROP DEFAULT;
ALTER TABLE galette_logs ALTER COLUMN id_log ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_logs', 'id_log'), max(id_log) FROM galette_logs;

DROP SEQUENCE IF EXISTS galette_field_types_id_seq;
ALTER TABLE galette_field_types ALTER COLUMN field_id DROP DEFAULT;
ALTER TABLE galette_field_types ALTER COLUMN field_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_field_types', 'field_id'), max(field_id) FROM galette_field_types;

DROP SEQUENCE IF EXISTS galette_types_cotisation_id_seq;
ALTER TABLE galette_types_cotisation ALTER COLUMN id_type_cotis DROP DEFAULT;
ALTER TABLE galette_types_cotisation ALTER COLUMN id_type_cotis ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_types_cotisation', 'id_type_cotis'), max(id_type_cotis) FROM galette_types_cotisation;
ALTER TABLE galette_types_cotisation ALTER COLUMN cotis_extension TYPE smallint;

DROP SEQUENCE IF EXISTS galette_groups_id_seq;
ALTER TABLE galette_groups ALTER COLUMN id_group DROP DEFAULT;
ALTER TABLE galette_groups ALTER COLUMN id_group ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_groups', 'id_group'), max(id_group) FROM galette_groups;

DROP SEQUENCE IF EXISTS galette_mailing_history_id_seq;
ALTER TABLE galette_mailing_history ALTER COLUMN mailing_id DROP DEFAULT;
ALTER TABLE galette_mailing_history ALTER COLUMN mailing_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_mailing_history', 'mailing_id'), max(mailing_id) FROM galette_mailing_history;

DROP SEQUENCE IF EXISTS galette_titles_id_seq;
ALTER TABLE galette_titles ALTER COLUMN id_title DROP DEFAULT;
ALTER TABLE galette_titles ALTER COLUMN id_title ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_titles', 'id_title'), max(id_title) FROM galette_titles;

DROP SEQUENCE IF EXISTS galette_reminders_id_seq;
ALTER TABLE galette_reminders ALTER COLUMN reminder_id DROP DEFAULT;
ALTER TABLE galette_reminders ALTER COLUMN reminder_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_reminders', 'reminder_id'), max(reminder_id) FROM galette_reminders;

DROP SEQUENCE IF EXISTS galette_pdfmodels_id_seq;
ALTER TABLE galette_pdfmodels ALTER COLUMN model_id DROP DEFAULT;
ALTER TABLE galette_pdfmodels ALTER COLUMN model_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_pdfmodels', 'model_id'), max(model_id) FROM galette_pdfmodels;

DROP SEQUENCE IF EXISTS galette_import_model_id_seq;
ALTER TABLE galette_import_model ALTER COLUMN model_id DROP DEFAULT;
ALTER TABLE galette_import_model ALTER COLUMN model_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_import_model', 'model_id'), max(model_id) FROM galette_import_model;

DROP SEQUENCE IF EXISTS galette_paymenttypes_id_seq;
ALTER TABLE galette_paymenttypes ALTER COLUMN type_id DROP DEFAULT;
ALTER TABLE galette_paymenttypes ALTER COLUMN type_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_paymenttypes', 'type_id'), max(type_id) FROM galette_paymenttypes;

DROP SEQUENCE IF EXISTS galette_searches_id_seq;
ALTER TABLE galette_searches ALTER COLUMN search_id DROP DEFAULT;
ALTER TABLE galette_searches ALTER COLUMN search_id ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_searches', 'search_id'), max(search_id) FROM galette_searches;

DROP SEQUENCE IF EXISTS galette_texts_id_seq;
ALTER TABLE galette_texts ALTER COLUMN tid DROP DEFAULT;
ALTER TABLE galette_texts ALTER COLUMN tid ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_texts', 'tid'), max(tid) FROM galette_texts;

DROP SEQUENCE IF EXISTS galette_fields_categories_id_seq;
ALTER TABLE galette_fields_categories ALTER COLUMN id_field_category DROP DEFAULT;
ALTER TABLE galette_fields_categories ALTER COLUMN id_field_category ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_fields_categories', 'id_field_category'), max(id_field_category) FROM galette_fields_categories;

DROP SEQUENCE IF EXISTS galette_socials_id_seq;
ALTER TABLE galette_socials ALTER COLUMN id_social DROP DEFAULT;
ALTER TABLE galette_socials ALTER COLUMN id_social ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_socials', 'id_social'), max(id_social) FROM galette_socials;

DROP SEQUENCE IF EXISTS galette_documents_id_seq;
ALTER TABLE galette_documents ALTER COLUMN id_document DROP DEFAULT;
ALTER TABLE galette_documents ALTER COLUMN id_document ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_documents', 'id_document'), max(id_document) FROM galette_documents;
ALTER TABLE galette_documents ALTER COLUMN visible TYPE smallint;

DROP SEQUENCE IF EXISTS galette_payments_schedules_id_seq;
ALTER TABLE galette_payments_schedules ALTER COLUMN id_schedule DROP DEFAULT;
ALTER TABLE galette_payments_schedules ALTER COLUMN id_schedule ADD GENERATED BY DEFAULT AS IDENTITY;
SELECT pg_get_serial_sequence('galette_payments_schedules', 'id_schedule'), max(id_schedule) FROM galette_payments_schedules;

ALTER TABLE galette_adherents ALTER COLUMN id_statut DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN id_statut SET NOT NULL;

ALTER TABLE galette_fields_categories ALTER COLUMN position TYPE smallint;

ALTER TABLE galette_fields_config ALTER COLUMN position TYPE smallint;
ALTER TABLE galette_fields_config ALTER COLUMN list_position TYPE smallint;
ALTER TABLE galette_fields_config ALTER COLUMN list_position SET NOT NULL;
ALTER TABLE galette_fields_config ALTER COLUMN width_in_forms TYPE smallint;

ALTER TABLE galette_adherents ALTER COLUMN date_echeance SET DEFAULT NULL;
ALTER TABLE galette_adherents ALTER COLUMN tel_adh SET DEFAULT NULL;
ALTER TABLE galette_adherents ALTER COLUMN gsm_adh SET DEFAULT NULL;
ALTER TABLE galette_adherents ALTER COLUMN email_adh SET DEFAULT NULL;
ALTER TABLE galette_adherents ALTER COLUMN sexe_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN sexe_adh SET NOT NULL;
ALTER TABLE galette_adherents ALTER COLUMN pref_lang DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN ddn_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN date_crea_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN date_modif_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN adresse_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN prof_adh SET DEFAULT NULL;
ALTER TABLE galette_adherents ALTER COLUMN lieu_naissance DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN lieu_naissance TYPE character varying(255);
ALTER TABLE galette_adherents ALTER COLUMN fingerprint SET DEFAULT NULL;
ALTER TABLE galette_adherents ALTER COLUMN mdp_adh SET DEFAULT '';
ALTER TABLE galette_adherents ALTER COLUMN mdp_adh SET NOT NULL;
ALTER TABLE galette_adherents ALTER COLUMN activite_adh SET NOT NULL;

ALTER TABLE galette_pdfmodels ALTER COLUMN model_type TYPE smallint;

ALTER TABLE galette_transactions ALTER COLUMN trans_date DROP DEFAULT;

ALTER TABLE galette_cotisations ALTER column date_enreg DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER column date_debut_cotis DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER column date_fin_cotis DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER column date_fin_cotis DROP NOT NULL;
ALTER TABLE galette_cotisations ALTER column id_adh SET NOT NULL;
ALTER TABLE galette_cotisations ALTER column id_adh DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER column id_type_cotis DROP DEFAULT;

ALTER TABLE galette_field_types ALTER COLUMN field_width_in_forms TYPE smallint;

ALTER TABLE galette_paymenttypes ALTER COLUMN type_name TYPE character varying(255);

ALTER TABLE galette_dynamic_fields ALTER COLUMN field_val DROP DEFAULT;
ALTER TABLE galette_dynamic_fields ALTER COLUMN field_id DROP DEFAULT;

ALTER TABLE galette_types_cotisation ALTER COLUMN libelle_type_cotis TYPE character varying(255);

ALTER TABLE galette_adherents DROP CONSTRAINT galette_adherents_id_statut_fkey,
   ADD CONSTRAINT galette_adherents_id_statut_fkey FOREIGN KEY (id_statut) REFERENCES galette_statuts(id_statut) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE galette_adherents DROP CONSTRAINT galette_adherents_parent_id_fkey,
   ADD CONSTRAINT galette_adherents_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES galette_adherents(id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE galette_adherents DROP CONSTRAINT galette_adherents_titre_adh_fkey,
   ADD CONSTRAINT galette_adherents_titre_adh_fkey FOREIGN KEY (titre_adh) REFERENCES galette_titles(id_title) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE galette_cotisations RENAME CONSTRAINT galette_cotisation_pkey TO galette_cotisations_pkey;
ALTER TABLE galette_cotisations ADD CONSTRAINT galette_cotisations_id_adh_fkey FOREIGN KEY (id_adh) REFERENCES galette_adherents(id_adh)  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE galette_cotisations ADD CONSTRAINT galette_cotisations_id_type_cotis_fkey FOREIGN KEY (id_type_cotis) REFERENCES galette_types_cotisation (id_type_cotis) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE galette_cotisations ADD CONSTRAINT galette_cotisations_trans_id_fkey FOREIGN KEY (trans_id) REFERENCES galette_transactions (trans_id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE galette_dynamic_fields ADD CONSTRAINT galette_dynamic_fields_field_id_fkey FOREIGN KEY (field_id) REFERENCES galette_field_types (field_id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE galette_tmppasswds DROP CONSTRAINT galette_tmppasswds_id_adh_fkey,
   ADD CONSTRAINT galette_tmppasswds_id_adh_fkey FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE galette_transactions ADD CONSTRAINT galette_transactions_id_adh_fkey FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE galette_transactions DROP CONSTRAINT type_paiement_trans_fkey, ADD CONSTRAINT galette_transactions_type_paiement_trans_fkey FOREIGN KEY (type_paiement_trans) REFERENCES galette_paymenttypes (type_id) ON DELETE RESTRICT ON UPDATE CASCADE;