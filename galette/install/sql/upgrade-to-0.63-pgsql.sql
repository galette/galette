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
DROP SEQUENCE galette_categories_id_seq;
CREATE SEQUENCE galette_categories_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;
 
DROP TABLE galette_info_categories;
CREATE TABLE galette_info_categories (
  id_cat integer DEFAULT nextval('galette_categories_id_seq'::text) NOT NULL,
  index_cat integer DEFAULT '0' NOT NULL,
  name_cat character varying(40) DEFAULT '' NOT NULL,
  perm_cat integer DEFAULT '0' NOT NULL,
  type_cat integer DEFAULT '0' NOT NULL,
  size_cat integer DEFAULT '1' NOT NULL,
  contents_cat text DEFAULT ''
);
CREATE UNIQUE INDEX galette_info_categories_idx ON galette_info_categories (id_cat);

DROP TABLE galette_adh_info; 
CREATE TABLE galette_adh_info (
  id_adh integer DEFAULT '0' NOT NULL,
  id_cat integer DEFAULT '0' NOT NULL,
  index_info integer DEFAULT '0' NOT NULL,
  val_info text DEFAULT ''
);
CREATE INDEX galette_ahd_info_idx ON galette_adh_info (id_adh);

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
