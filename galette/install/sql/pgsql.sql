DROP SEQUENCE galette_adherents_id_seq;
CREATE SEQUENCE galette_adherents_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE galette_cotisations_id_seq;
CREATE SEQUENCE galette_cotisations_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE galette_preferences_id_seq;
CREATE SEQUENCE galette_preferences_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE galette_adherents;
CREATE TABLE galette_adherents (
    id_adh integer DEFAULT nextval('galette_adherents_id_seq'::text) NOT NULL,
    id_statut integer DEFAULT '4' NOT NULL,
    nom_adh character varying(20) DEFAULT '' NOT NULL,
    prenom_adh character varying(20) DEFAULT NULL,
    pseudo_adh character varying(20) DEFAULT NULL,
    titre_adh smallint DEFAULT '0' NOT NULL,
    ddn_adh character varying(10) DEFAULT NULL,
    adresse_adh character varying(150) DEFAULT '' NOT NULL,
    adresse2_adh character varying(150) DEFAULT NULL,
    cp_adh character varying(10) DEFAULT '' NOT NULL,
    ville_adh character varying(50) DEFAULT '' NOT NULL,
    pays_adh character varying(50) DEFAULT NULL,
    tel_adh character varying(20),
    gsm_adh character varying(20),
    email_adh character varying(150),
    url_adh character varying(200),
    icq_adh character varying(20),
    msn_adh character varying(150),
    jabber_adh character varying(150),
    info_adh text,
    info_public_adh text,
    prof_adh character varying(150),
    login_adh character varying(20) DEFAULT '' NOT NULL,
    mdp_adh character varying(20) DEFAULT '' NOT NULL,
    date_crea_adh date DEFAULT '00000101' NOT NULL,
    activite_adh character(1) DEFAULT '0' NOT NULL,
    bool_admin_adh character(1) DEFAULT NULL,
    bool_exempt_adh character(1) DEFAULT NULL,
    bool_display_info character(1) DEFAULT NULL,
    date_echeance date,
    pref_lang character varying(20) DEFAULT 'french',
    lieu_naissance text DEFAULT '',
    gpgid character varying(8) DEFAULT NULL,
    fingerprint character varying(50) DEFAULT NULL
);
CREATE UNIQUE INDEX galette_adherents_idx ON galette_adherents (id_adh);
CREATE UNIQUE INDEX galette_login_idx     ON galette_adherents (login_adh);

DROP TABLE galette_cotisations;
CREATE TABLE galette_cotisations (
    id_cotis integer DEFAULT nextval('galette_cotisations_id_seq'::text)  NOT NULL,
    id_adh integer DEFAULT '0' NOT NULL,
    id_type_cotis integer DEFAULT '0' NOT NULL,
    montant_cotis real DEFAULT '0',
    info_cotis text,
    date_enreg date DEFAULT '00000101' NOT NULL,
    date_debut_cotis date DEFAULT '00000101' NOT NULL,
    date_fin_cotis date DEFAULT '00000101' NOT NULL
);
CREATE UNIQUE INDEX galette_cotisations_idx ON galette_cotisations (id_cotis);

DROP TABLE galette_statuts;
CREATE TABLE galette_statuts (
  id_statut integer NOT NULL,
  libelle_statut  character varying(20) DEFAULT '' NOT NULL,
  priorite_statut smallint DEFAULT '0' NOT NULL
);
CREATE UNIQUE INDEX galette_statuts_idx ON galette_statuts (id_statut);

INSERT INTO galette_statuts VALUES (1,'Président',0);
INSERT INTO galette_statuts VALUES (2,'Trésorier',10);
INSERT INTO galette_statuts VALUES (4,'Membre actif',30);
INSERT INTO galette_statuts VALUES (5,'Membre bienfaiteur',40);
INSERT INTO galette_statuts VALUES (6,'Membre fondateur',50);
INSERT INTO galette_statuts VALUES (3,'Secrétaire',20);
INSERT INTO galette_statuts VALUES (7,'Ancien',60);
INSERT INTO galette_statuts VALUES (8,'Personne morale',70);
INSERT INTO galette_statuts VALUES (9,'Non membre',80);
INSERT INTO galette_statuts VALUES (10,'Vice-président',5);

DROP TABLE galette_types_cotisation;
CREATE TABLE galette_types_cotisation (
  id_type_cotis integer NOT NULL,
  libelle_type_cotis character varying(30) DEFAULT '' NOT NULL,
  cotis_extension character(1) DEFAULT NULL
);
CREATE UNIQUE INDEX galette_types_cotisation_idx ON galette_types_cotisation (id_type_cotis);

INSERT INTO galette_types_cotisation VALUES (1, 'Cotisation annuelle normale', '1');
INSERT INTO galette_types_cotisation VALUES (2, 'Cotisation annuelle réduite', '1');
INSERT INTO galette_types_cotisation VALUES (3, 'Cotisation entreprise', '1');
INSERT INTO galette_types_cotisation VALUES (4, 'Donation en nature', NULL);
INSERT INTO galette_types_cotisation VALUES (5, 'Donation pécunière', NULL);
INSERT INTO galette_types_cotisation VALUES (6, 'Partenariat', NULL);
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)', '1');

DROP TABLE galette_preferences;
CREATE TABLE galette_preferences (
  id_pref integer DEFAULT nextval('galette_preferences_id_seq'::text) NOT NULL,
  nom_pref character varying(100) DEFAULT '' NOT NULL,
  val_pref character varying(200) DEFAULT '' NOT NULL
);
CREATE UNIQUE INDEX galette_preferences_idx ON galette_preferences (id_pref);

DROP SEQUENCE galette_logs_id_seq;
CREATE SEQUENCE galette_logs_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE galette_logs;
CREATE TABLE galette_logs (
  id_log integer DEFAULT nextval('galette_logs_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  ip_log character varying(30) DEFAULT '' NOT NULL,
  adh_log character varying(41) DEFAULT '' NOT NULL,
  text_log text
);
CREATE UNIQUE INDEX galette_logs_idx ON galette_logs (id_log);

-- Sequence for dynamic fields description;
DROP SEQUENCE galette_field_types_id_seq;
CREATE SEQUENCE galette_field_types_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for dynamic fields description;
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

-- Table for dynamic fields data;
DROP TABLE galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
  item_id integer DEFAULT '0' NOT NULL,
  field_id integer DEFAULT '0' NOT NULL,
  field_form character varying(10) NOT NULL,
  val_index integer DEFAULT '0' NOT NULL,
  field_val text DEFAULT ''
);
CREATE INDEX galette_dynamic_fields_item_idx ON galette_dynamic_fields (item_id);

DROP TABLE galette_pictures;
CREATE TABLE galette_pictures (
  id_adh integer DEFAULT '0' NOT NULL,
  picture bytea NOT NULL,
  format character varying(30) DEFAULT '' NOT NULL,
  width integer DEFAULT '0' NOT NULL,
  height integer DEFAULT '0' NOT NULL
);
CREATE INDEX galette_pictures_idx ON galette_pictures (id_adh);
