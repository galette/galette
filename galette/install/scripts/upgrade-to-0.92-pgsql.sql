--change account creation email
UPDATE galette_texts SET tbody = 'Hello,\r\n\r\nYou''ve just been subscribed on the members management system of {ASSO_NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this email was sent automatically)' WHERE tref = 'sub' AND tlang = 'en_US';

UPDATE galette_texts SET tbody = 'Bonjour,\r\n\r\nVous venez d''adhérer à {ASSO_NAME}.\r\n\r\nVous pouvez désormais suivre en temps réel l''état de vos souscriptions et mettre à jour vos coordonnées depuis l''interface web.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe :\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nLe lien ci-dessus sera valide jusqu''au {LINK_VALIDITY}.\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)' WHERE tref = 'sub' AND tlang = 'fr_FR';

-- sequence for payment types
DROP SEQUENCE IF EXISTS galette_paymenttypes_id_seq;
CREATE SEQUENCE galette_paymenttypes_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for payment types
DROP TABLE IF EXISTS galette_paymenttypes;
CREATE TABLE galette_paymenttypes (
  type_id integer DEFAULT nextval('galette_paymenttypes_id_seq'::text) NOT NULL,
  type_name character varying(50) NOT NULL,
  PRIMARY KEY (type_id)
);

INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (6, 'Other');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (1, 'Cash');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (2, 'Credit card');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (3, 'Check');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (4, 'Transfer');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (5, 'Paypal');

UPDATE galette_cotisations SET type_paiement_cotis = 6 WHERE type_paiement_cotis = 0;

ALTER TABLE galette_cotisations ALTER COLUMN type_paiement_cotis TYPE integer;
ALTER TABLE galette_cotisations ALTER COLUMN type_paiement_cotis DROP DEFAULT;
ALTER TABLE galette_cotisations
    ADD CONSTRAINT galette_cotisations_type_paiement_cotis_fkey
        FOREIGN KEY (type_paiement_cotis) REFERENCES galette_paymenttypes(type_id)
            ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE galette_database SET version = 0.92;
