ALTER TABLE adherents ADD jabber_adh character varying(150);
ALTER TABLE adherents ADD bool_display_info character(1) DEFAULT NULL;
ALTER TABLE adherents ADD info_public_adh text;
ALTER TABLE adherents ADD pays_adh character varying(50) DEFAULT NULL;
ALTER TABLE adherents ADD adresse2_adh character varying(150) DEFAULT NULL;

CREATE SEQUENCE logs_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

CREATE TABLE logs (
  id_log integer DEFAULT nextval('logs_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  ip_log character varying(30) DEFAULT '' NOT NULL,
  adh_log character varying(41) DEFAULT '' NOT NULL,
  text_log text
);

DELETE FROM statuts;
INSERT INTO statuts VALUES (1,'Président',0);
INSERT INTO statuts VALUES (10,'Vice-président',5);
INSERT INTO statuts VALUES (2,'Trésorier',10);
INSERT INTO statuts VALUES (4,'Membre actif',30);
INSERT INTO statuts VALUES (5,'Membre bienfaiteur',40);
INSERT INTO statuts VALUES (6,'Membre fondateur',50);
INSERT INTO statuts VALUES (3,'Secrétaire',20);
INSERT INTO statuts VALUES (7,'Ancien',60);
INSERT INTO statuts VALUES (8,'Personne morale',70);
INSERT INTO statuts VALUES (9,'Non membre',80);
