-- $Id$
ALTER TABLE galette_adherents ADD pref_lang character varying(20);
ALTER TABLE galette_adherents ALTER pref_lang SET DEFAULT 'french';
ALTER TABLE galette_adherents ALTER ddn_adh SET DEFAULT '19010101';
ALTER TABLE galette_adherents ALTER date_crea_adh SET DEFAULT '19010101';
ALTER TABLE galette_adherents ADD lieu_naissance character varying(20);
ALTER TABLE galette_adherents ALTER lieu_naissance SET DEFAULT '';
ALTER TABLE galette_adherents ADD gpgid character varying(8);
ALTER TABLE galette_adherents ADD fingerprint character varying(50);

-- to test;
ALTER TABLE galette_adherents ADD mdp_temp_adh character varying(40);
UPDATE galette_adherents SET mdp_temp_adh = CAST(mdp_adh AS character varying(40));
ALTER TABLE galette_adherents DROP COLUMN mdp_adh;
ALTER TABLE galette_adherents RENAME mdp_temp_adh TO mdp_adh;


CREATE TABLE galette_pictures (
    id_adh integer DEFAULT 0 NOT NULL,
    picture bytea NOT NULL,
    format character varying(30) DEFAULT ''::character varying NOT NULL
);

-- stephs ;
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
CREATE UNIQUE INDEX galette_adherents_idx ON galette_adherents (id_adh);
CREATE UNIQUE INDEX galette_login_idx     ON galette_adherents (login_adh);
CREATE UNIQUE INDEX galette_cotisations_idx ON galette_cotisations (id_cotis);
CREATE UNIQUE INDEX galette_statuts_idx ON galette_statuts (id_statut);
CREATE UNIQUE INDEX galette_types_cotisation_idx ON galette_types_cotisation (id_type_cotis);
CREATE UNIQUE INDEX galette_logs_idx ON galette_logs (id_log);

-- Fix table preference with duplicate ids and create index;
-- Cause problems with aldil dump(stephs) ;
-- UPDATE galette_preferences SET id_pref=id_pref+1 WHERE (id_pref >= 4 AND nom_pref != 'pref_ville');
-- UPDATE galette_preferences SET id_pref=id_pref+1 WHERE (id_pref >= 2 AND nom_pref != 'pref_adresse');
CREATE UNIQUE INDEX galette_preferences_idx ON galette_preferences (id_pref);
-- Add new or missing preferences;
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_pays', '-');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_website', '');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_mail_method', '0');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_mail_smtp', '0'); 
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_membership_ext', '12');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_beg_membership', '');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_email_reply_to', '');

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
ALTER TABLE galette_cotisations ALTER COLUMN date_enreg SET DEFAULT '19010101';
ALTER TABLE galette_cotisations ALTER COLUMN date_debut_cotis SET NOT NULL;
ALTER TABLE galette_cotisations ALTER COLUMN date_debut_cotis SET DEFAULT '19010101';
ALTER TABLE galette_cotisations ALTER COLUMN date_fin_cotis SET NOT NULL;
ALTER TABLE galette_cotisations ALTER COLUMN date_fin_cotis SET DEFAULT '19010101';
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

-- Table for transactions;
DROP SEQUENCE galette_transactions_id_seq;
CREATE SEQUENCE galette_transactions_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE galette_transactions;
CREATE TABLE galette_transactions (
    trans_id integer DEFAULT nextval('galette_transactions_id_seq'::text)  NOT NULL,
    trans_date date DEFAULT '19010101' NOT NULL,
    trans_amount real DEFAULT '0',
    trans_desc character varying(30) NOT NULL DEFAULT '',
    id_adh integer DEFAULT NULL
);
CREATE UNIQUE INDEX galette_transactions_idx ON galette_transactions (trans_id);

ALTER TABLE galette_cotisations ADD trans_id integer;
ALTER TABLE galette_cotisations ALTER COLUMN trans_id SET DEFAULT NULL;

-- new table for temporary passwords  2006-02-18;
DROP TABLE galette_tmppasswds;
CREATE TABLE galette_tmppasswds (
    id_adh integer NOT NULL,
    tmp_passwd character varying(40) NOT NULL,
    date_crea_tmp_passwd timestamp NOT NULL
);
CREATE UNIQUE INDEX galette_tmppasswds_idx ON galette_tmppasswds (id_adh);

-- 0.63 now uses md5 hash for passwords
UPDATE galette_adherents SET mdp_adh = md5(mdp_adh) WHERE length(mdp_adh) <> 32;

--
