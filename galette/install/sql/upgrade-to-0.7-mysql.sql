-- Add new or missing preferences;
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_slogan', '');
UPDATE galette_preferences SET val_pref='fr_FR' WHERE nom_pref='french';
UPDATE galette_preferences SET val_pref='en_EN' WHERE nom_pref='english';
UPDATE galette_preferences SET val_pref='es_ES' WHERE nom_pref='spanish';
UPDATE galette_adherents SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_adherents SET pref_lang='en_EN' WHERE pref_lang='english';
UPDATE galette_adherents SET pref_lang='es_ES' WHERE pref_lang='spanish';

-- Table for dynamic required fields 2007-07-10;
DROP TABLE IF EXISTS galette_required;
CREATE TABLE galette_required (
	field_id varchar(15) NOT NULL,
	required tinyint(1) NOT NULL,
	PRIMARY KEY  (`field_id`)
) TYPE=MyISAM;

-- Add new table for automatic mails and their translations
DROP TABLE IF EXISTS `galette_texts`;
CREATE TABLE IF NOT EXISTS `galette_texts` (
  `tid` smallint(6) NOT NULL auto_increment,
  `tref` varchar(20) NOT NULL,
  `tsubject` varchar(256) NOT NULL,
  `tbody` text NOT NULL,
  `tlang` varchar(16) NOT NULL,
  `tcomment` varchar(64) NOT NULL,
  PRIMARY KEY  (`tid`)
) TYPE=MyISAM;

-- Modify table picture to allow for negative indexes
ALTER TABLE `galette_pictures` CHANGE `id_adh` `id_adh` INT( 10 ) NOT NULL DEFAULT '0';

-- Add a new table to store models descriptions for documents
DROP TABLE IF EXISTS `galette_models`;
CREATE TABLE IF NOT EXISTS `galette_models` (
  `mod_id` int(11) NOT NULL COMMENT 'id du modèle',
  `mod_carac` varchar(64) NOT NULL COMMENT 'caracteristique',
  `carac_id` varchar(32) default NULL COMMENT 'id caractéristique',
  `carac_type` varchar(32) NOT NULL COMMENT 'type',
  `carac_value` varchar(256) NOT NULL COMMENT 'valeur',
  `carac_xpath` varchar(256) NOT NULL,
  `carac_cond_id` int(11) default NULL COMMENT 'index sur condition',
  PRIMARY KEY  (`mod_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Modèles des documents';

-- Add a new table to store conditionals fields for models
DROP TABLE IF EXISTS `galette_models_conditions`;
CREATE TABLE IF NOT EXISTS `galette_models_conditions` (
  `cond_id` int(11) NOT NULL,
  `cond_field` varchar(32) NOT NULL,
  `cond_in` varchar(64) NOT NULL,
  `cond_out` varchar(64) NOT NULL,
  PRIMARY KEY  (`cond_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Contenu de la table `galette_texts`
-- 

INSERT INTO `galette_texts` (`tid`, `tref`, `tsubject`, `tbody`, `tlang`, `tcomment`) VALUES 
(1, 'sub', 'Your identifiers', 'Hello,\r\n\r\nYou''ve just been subscribed on the members management system of {NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'New user registration'),
(2, 'sub', 'Votre adhésion', 'Bonjour,\r\n\r\nVous venez d''adhérer à {NAME}.\r\n\r\nVous pouvez désormais accéder à vos coordonnées et souscriptions en vous connectant à l''adresse suivante:\r\n\r\n{LOGIN_URI} \r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe: {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)', 'fr_FR', 'Nouvelle adhésion'),
(3, 'sub', 'Sus identificaciones', 'Hola,\r\n\r\nAcaba de ser dado de alta en el sistema de gestión de socios de la asociación {NAME}.\r\n\r\nAhora puede seguir en tiempo real el estado de su inscripción y actualizar sus preferencias usando la interfaz web prevista con este fin:\r\n\r\n{LOGIN_URI} \r\n\r\nNombre de usuario: {LOGIN}\r\nContraseña: {PASSWORD}\r\n\r\n¡Hasta pronto!\r\n\r\n(este correo ha sido enviado automáticamente)', 'es_ES', 'Nuevo .....????'),
(4, 'pwd', 'Your identifiers', 'Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nTemporary password: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'Lost password email'),
(5, 'pwd', 'Vos Identifiants', 'Bonjour,\r\n\r\nQuelqu''un (probablement vous) a demandé la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe Temporaire: {PASSWORD}\r\n\r\nA Bientôt!\r\n\r\n(Courrier envoyé automatiquement)', 'fr_FR', 'Récupération du mot de passe'),
(6, 'pwd', 'Sus identificaciones', 'Hola,\r\n\r\nAlguien (probablemente usted) pidió que se le reenviase su contraseña.\r\n\r\nPor favor identifíquese usted en esta dirección para modificar su contraseña:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nContraseña provisional: {PASSWORD}\r\n\r\n¡Hasta pronto!\r\n\r\n(este correo ha sido enviado automáticamente)', 'es_ES', 'Recuperación de la contraseña');
