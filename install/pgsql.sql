DROP SEQUENCE adherents_id_seq;
CREATE SEQUENCE adherents_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE cotisations_id_seq;
CREATE SEQUENCE cotisations_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE adherents;
CREATE TABLE adherents (
    id_adh integer DEFAULT nextval('adherents_id_seq'::text) NOT NULL,
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

DROP TABLE cotisations;
CREATE TABLE cotisations (
    id_cotis integer DEFAULT nextval('cotisations_id_seq'::text)  NOT NULL,
    id_adh integer DEFAULT '0' NOT NULL,
    id_type_cotis integer DEFAULT '0' NOT NULL,
    montant_cotis real DEFAULT '0',
    info_cotis text,
    duree_mois_cotis smallint DEFAULT '12' NOT NULL,
    date_cotis date NOT NULL
);

DROP TABLE statuts;
CREATE TABLE statuts (
  id_statut integer NOT NULL,
  libelle_statut  character varying(20) DEFAULT '' NOT NULL,
  priorite_statut smallint DEFAULT '0' NOT NULL
);

INSERT INTO statuts VALUES (1,'Président',0);
INSERT INTO statuts VALUES (2,'Trésorier',10);
INSERT INTO statuts VALUES (4,'Membre actif',30);
INSERT INTO statuts VALUES (5,'Membre bienfaiteur',40);
INSERT INTO statuts VALUES (6,'Membre fondateur',50);
INSERT INTO statuts VALUES (3,'Secrétaire',20);
INSERT INTO statuts VALUES (7,'Ancien',60);
INSERT INTO statuts VALUES (8,'Personne morale',70);
INSERT INTO statuts VALUES (9,'Non membre',80);
INSERT INTO statuts VALUES (10,'Vice-président',5);

DROP TABLE types_cotisation;
CREATE TABLE types_cotisation (
  id_type_cotis integer NOT NULL,
  libelle_type_cotis character varying(30) DEFAULT '' NOT NULL
);

INSERT INTO types_cotisation VALUES (1,'Cotisation annuelle normale');
INSERT INTO types_cotisation VALUES (2,'Cotisation annuelle réduite');
INSERT INTO types_cotisation VALUES (3,'Cotisation entreprise');
INSERT INTO types_cotisation VALUES (4,'Donation en nature');
INSERT INTO types_cotisation VALUES (5,'Donation pécunière');
INSERT INTO types_cotisation VALUES (6,'Partenariat');

DROP TABLE preferences;
CREATE TABLE preferences (
  pref_nom character varying(40) DEFAULT '' NOT NULL,
  pref_adresse character varying(150) DEFAULT '' NOT NULL,
  pref_adresse2 character varying(150) DEFAULT NULL,
  pref_cp character varying(10) DEFAULT '' NOT NULL,
  pref_ville character varying(50) DEFAULT '' NOT NULL,
  pref_pays character varying(50) DEFAULT NULL,
  pref_lang character varying(20) DEFAULT '' NOT NULL,
  pref_numrows integer DEFAULT '30' NOT NULL,
  pref_log character(1) DEFAULT '1' NOT NULL,
  pref_email_nom character varying(20) DEFAULT '' NOT NULL,
  pref_email character varying(150) DEFAULT '' NOT NULL,
  pref_etiq_marges integer DEFAULT '0' NOT NULL,
  pref_etiq_hspace integer DEFAULT '0' NOT NULL,
  pref_etiq_vspace integer DEFAULT '0' NOT NULL,
  pref_etiq_hsize integer DEFAULT '0' NOT NULL,
  pref_etiq_vsize integer DEFAULT '0' NOT NULL,
  pref_etiq_cols integer DEFAULT '0' NOT NULL,
  pref_etiq_rows integer DEFAULT '0' NOT NULL,
  pref_etiq_corps integer DEFAULT '0' NOT NULL,
  pref_admin_login character varying(20) DEFAULT '' NOT NULL,
  pref_admin_pass character varying(20) DEFAULT '' NOT NULL
);

DROP SEQUENCE logs_id_seq;
CREATE SEQUENCE logs_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE logs;
CREATE TABLE logs (
  id_log integer DEFAULT nextval('logs_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  ip_log character varying(30) DEFAULT '' NOT NULL,
  adh_log character varying(41) DEFAULT '' NOT NULL,
  text_log text
);


