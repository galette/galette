ALTER TABLE galette_adherents ADD pref_lang character varying(20);
ALTER TABLE galette_adherents ALTER pref_lang SET DEFAULT 'french';
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
CREATE UNIQUE INDEX galette_adherents_idx ON galette_adherents (id_adh);
CREATE UNIQUE INDEX galette_login_idx     ON galette_adherents (login_adh);
CREATE UNIQUE INDEX galette_cotisations_idx ON galette_cotisations (id_cotis);
CREATE UNIQUE INDEX galette_statuts_idx ON galette_statuts (id_statut);
CREATE UNIQUE INDEX galette_types_cotisation_idx ON galette_types_cotisation (id_type_cotis);
CREATE UNIQUE INDEX galette_logs_idx ON galette_logs (id_log);

DELETE FROM galette_preferences WHERE nom_pref == 'pref_pays';
CREATE UNIQUE INDEX galette_preferences_idx ON galette_preferences (id_pref);
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_pays', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_method', '0');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp', '0');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_membership_ext', '12');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_beg_membership', '');

CREATE SEQUENCE galette_categories_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;
 
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
 
CREATE TABLE galette_adh_info (
  id_adh integer DEFAULT '0' NOT NULL,
  id_cat integer DEFAULT '0' NOT NULL,
  index_info integer DEFAULT '0' NOT NULL,
  val_info text DEFAULT ''
);
CREATE INDEX galette_ahd_info_idx ON galette_adh_info (id_adh);
