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
    ddn_adh date,
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
    date_crea_adh date NOT NULL,
    activite_adh character(1) DEFAULT '0' NOT NULL,
    bool_admin_adh character(1) DEFAULT NULL,
    bool_exempt_adh character(1) DEFAULT NULL,
    bool_display_info character(1) DEFAULT NULL,
    date_echeance date
);

DROP TABLE galette_cotisations;
CREATE TABLE galette_cotisations (
    id_cotis integer DEFAULT nextval('galette_cotisations_id_seq'::text)  NOT NULL,
    id_adh integer DEFAULT '0' NOT NULL,
    id_type_cotis integer DEFAULT '0' NOT NULL,
    montant_cotis real DEFAULT '0',
    info_cotis text,
    duree_mois_cotis smallint DEFAULT '12' NOT NULL,
    date_cotis date NOT NULL
);

DROP TABLE galette_statuts;
CREATE TABLE galette_statuts (
  id_statut integer NOT NULL,
  libelle_statut  character varying(20) DEFAULT '' NOT NULL,
  priorite_statut smallint DEFAULT '0' NOT NULL
);

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
  libelle_type_cotis character varying(30) DEFAULT '' NOT NULL
);

INSERT INTO galette_types_cotisation VALUES (1,'Cotisation annuelle normale');
INSERT INTO galette_types_cotisation VALUES (2,'Cotisation annuelle réduite');
INSERT INTO galette_types_cotisation VALUES (3,'Cotisation entreprise');
INSERT INTO galette_types_cotisation VALUES (4,'Donation en nature');
INSERT INTO galette_types_cotisation VALUES (5,'Donation pécunière');
INSERT INTO galette_types_cotisation VALUES (6,'Partenariat');

DROP TABLE galette_preferences;
CREATE TABLE galette_preferences (
  id_pref integer DEFAULT nextval('galette_preferences_id_seq'::text) NOT NULL,
  nom_pref character varying(100) DEFAULT '' NOT NULL,
  val_pref character varying(200) DEFAULT '' NOT NULL
);

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


