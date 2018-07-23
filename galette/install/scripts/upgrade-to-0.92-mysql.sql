SET FOREIGN_KEY_CHECKS=0;

-- change account creation mail
UPDATE galette_texts SET tbody = "Hello,\r\n\r\nYou\'ve just been subscribed on the members management system of {ASSO_NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)" WHERE tref = 'sub' AND tlang = 'en_US';

UPDATE galette_texts SET tbody = "Bonjour,\r\n\r\nVous venez d\'adhérer à {ASSO_NAME}.\r\n\r\nVous pouvez désormais suivre en temps réel l\'état de vos souscriptions et mettre à jour vos coordonnées depuis l\'interface web.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe :\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nLe lien ci-dessus sera valide jusqu\'au {LINK_VALIDITY}.\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)" WHERE tref = 'sub' AND tlang = 'fr_FR';

-- switch engine
ALTER TABLE galette_preferences ENGINE=InnoDB;
ALTER TABLE galette_logs ENGINE=InnoDB;
ALTER TABLE galette_l10n ENGINE=InnoDB;
ALTER TABLE galette_texts ENGINE=InnoDB;
ALTER TABLE galette_database ENGINE=InnoDB;

-- Table for payment types
DROP TABLE IF EXISTS galette_paymenttypes;
CREATE TABLE galette_paymenttypes (
  type_id int(10) unsigned NOT NULL auto_increment,
  type_name varchar(255) NOT NULL,
  PRIMARY KEY (type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (6, 'Other');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (1, 'Cash');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (2, 'Credit card');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (3, 'Check');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (4, 'Transfer');
INSERT INTO galette_paymenttypes(type_id, type_name) VALUES (5, 'Paypal');

UPDATE galette_cotisations SET type_paiement_cotis = 6 WHERE type_paiement_cotis = 0;

ALTER TABLE galette_cotisations CHANGE type_paiement_cotis type_paiement_cotis INT(10) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE galette_cotisations ADD FOREIGN KEY (type_paiement_cotis) REFERENCES galette_paymenttypes(type_id) ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE galette_database SET version = 0.92;
SET FOREIGN_KEY_CHECKS=1;
