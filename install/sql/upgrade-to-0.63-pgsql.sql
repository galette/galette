ALTER TABLE galette_adherents ADD pref_lang character varying(20);
ALTER TABLE galette_adherents ALTER pref_lang SET DEFAULT 'french';
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
CREATE UNIQUE INDEX galette_adherents_idx ON galette_adherents (id_adh);
CREATE UNIQUE INDEX galette_login_idx     ON galette_adherents (login_adh);
CREATE UNIQUE INDEX galette_cotisations_idx ON galette_cotisations (id_cotis);
CREATE UNIQUE INDEX galette_statuts_idx ON galette_statuts (id_statut);
CREATE UNIQUE INDEX galette_types_cotisation_idx ON galette_types_cotisation (id_type_cotis);
CREATE UNIQUE INDEX galette_logs_idx ON galette_logs (id_log);

-- Fix table preference with duplicate ids and create index;
UPDATE galette_preferences SET id_pref=id_pref+1 WHERE (id_pref >= 4 AND nom_pref != 'pref_ville');
UPDATE galette_preferences SET id_pref=id_pref+1 WHERE (id_pref >= 2 AND nom_pref != 'pref_adresse');
CREATE UNIQUE INDEX galette_preferences_idx ON galette_preferences (id_pref);
-- Add new or missing preferences
INSERT INTO galette_preferences VALUES (22, 'pref_mail_method', '0');
INSERT INTO galette_preferences VALUES (23, 'pref_mail_smtp', '0');
INSERT INTO galette_preferences VALUES (24, 'pref_membership_ext', '12');
INSERT INTO galette_preferences VALUES (25, 'pref_beg_membership', '');

-- New tables for dynamic fields;
DROP SEQUENCE galette_field_types_id_seq;
CREATE SEQUENCE galette_field_types_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE galette_field_types;
CREATE TABLE galette_field_types (
  field_id integer DEFAULT nextval('galette_field_types_id_seq'::text) NOT NULL,
  field_form character varying(10) NOT NULL,
  field_index integer DEFAULT '0' NOT NULL,
  field_name character varying(40) DEFAULT '' NOT NULL,
  field_perm integer DEFAULT '0' NOT NULL,
  field_type integer DEFAULT '0' NOT NULL,
  field_required character(1) DEFAULT NULL,
  field_pos integer DEFAULT '0' NOT NULL,
  field_width integer DEFAULT NULL,
  field_height integer DEFAULT NULL,
  field_size integer DEFAULT NULL,
  field_repeat integer DEFAULT NULL,
  field_layout integer DEFAULT NULL
);
CREATE UNIQUE INDEX galette_field_types_idx ON galette_field_types (field_id);
CREATE INDEX galette_field_types_form_idx ON galette_field_types (field_form);

DROP TABLE galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
  item_id integer DEFAULT '0' NOT NULL,
  field_id integer DEFAULT '0' NOT NULL,
  field_form character varying(10) NOT NULL,
  val_index integer DEFAULT '0' NOT NULL,
  field_val text DEFAULT ''
);
CREATE INDEX galette_dynamic_fields_item_idx ON galette_dynamic_fields (item_id);

-- Add two fields for logs;
ALTER TABLE galette_logs ADD action_log text;
ALTER TABLE galette_logs ADD sql_log text;

-- Change table cotisations to store date_fin_cotis instead of duration;
ALTER TABLE galette_cotisations ADD date_enreg date;
ALTER TABLE galette_cotisations ADD date_debut_cotis date;
ALTER TABLE galette_cotisations ADD date_fin_cotis date;
UPDATE galette_cotisations
	SET date_enreg=date_cotis,
	    date_debut_cotis=date_cotis,
	    date_fin_cotis=date_cotis +  to_char(duree_mois_cotis, '99" month"')::interval;
ALTER TABLE galette_cotisations ALTER COLUMN date_enreg SET NOT NULL;
ALTER TABLE galette_cotisations ALTER COLUMN date_enreg SET DEFAULT '00000101';
ALTER TABLE galette_cotisations ALTER COLUMN date_debut_cotis SET NOT NULL;
ALTER TABLE galette_cotisations ALTER COLUMN date_debut_cotis SET DEFAULT '00000101';
ALTER TABLE galette_cotisations ALTER COLUMN date_fin_cotis SET NOT NULL;
ALTER TABLE galette_cotisations ALTER COLUMN date_fin_cotis SET DEFAULT '00000101';
ALTER TABLE galette_cotisations DROP duree_mois_cotis;
ALTER TABLE galette_cotisations DROP date_cotis;

-- Add column to galette_types_cotisations;
ALTER TABLE galette_types_cotisation ADD cotis_extension character(1);
ALTER TABLE galette_types_cotisation ALTER COLUMN cotis_extension SET DEFAULT NULL;
UPDATE galette_types_cotisation SET cotis_extension=1 WHERE
	id_type_cotis <= 3 OR id_type_cotis = 7;

-- Table for dynamic translation of strings;
DROP TABLE galette_l10n;
CREATE TABLE galette_l10n (
  text_orig character varying(40) NOT NULL,
  text_locale character varying(15) NOT NULL,
  text_nref integer DEFAULT '1' NOT NULL,
  text_trans character varying(40) DEFAULT '' NOT NULL
);
CREATE UNIQUE INDEX galette_l10n_idx ON galette_l10n (text_orig, text_locale);
