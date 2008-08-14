-- Add new or missing preferences;
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_slogan', '');
UPDATE galette_preferences SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_preferences SET pref_lang='en_EN' WHERE pref_lang='english';
UPDATE galette_preferences SET pref_lang='es_ES' WHERE pref_lang='spanish';
UPDATE galette_adherents SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_adherents SET pref_lang='en_EN' WHERE pref_lang='english';
UPDATE galette_adherents SET pref_lang='es_ES' WHERE pref_lang='spanish';
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_abrev', 'GALETTE');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_strip','Gestion d Adherents en Ligne Extrêmement Tarabiscoté');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_tcol', 'FFFFFF');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_scol', '8C2453');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_bcol', '53248C');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_hcol', '248C53');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_bool_display_title', '');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_address', '1');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_year', '2007');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_marges_v', '15');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_marges_h', '20');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_vspace', '5');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_hspace', '10');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_card_self', '1');
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_editor_enabled', '');


-- Table for dynamic required fields 2007-07-10;
DROP TABLE galette_required;
CREATE TABLE galette_required (
	field_id  character varying(20) NOT NULL,
	required boolean DEFAULT false NOT NULL
);
CREATE UNIQUE INDEX galette_required_idx ON galette_required (field_id);

-- Add new table for automatic mails and their translations
DROP TABLE galette_texts;
CREATE TABLE galette_texts (
  tid integer(6) DEFAULT nextval('galette_texts_id_seq'::text) NOT NULL,
  tref character varying(20) NOT NULL,
  tsubject character varying(256) NOT NULL,
  tbody text NOT NULL,
  tlang character varying(16) NOT NULL,
  tcomment character varying(64) NOT NULL
);
CREATE UNIQUE INDEX galette_texts_idx ON galette_texts (tid);

-- Modify table picture to allow for negative indexes
-- Nécéssaire ??
-- ALTER TABLE galette_pictures ALTER id_adh id_adh INT( 10 ) NOT NULL DEFAULT '0' 

-- Add a new table to store models descriptions for documents
DROP TABLE galette_models;
CREATE TABLE galette_models (
  mod_id integer NOT NULL,
  mod_name character varying(64)  NOT NULL,
  mod_xml text collate NOT NULL,
  PRIMARY KEY  (mod_id)
);
CREATE UNIQUE INDEX galette_models_idx ON galette_models (mod_id);

-- 
-- Contenu de la table `galette_texts`
-- 
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES 
(1, 'sub', 'Your identifiers', 'Hello,\r\n\r\nYou''ve just been subscribed on the members management system of {NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'New user registration');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (2, 'sub', 'Votre adhésion', 'Bonjour,\r\n\r\nVous venez d''adhérer à {NAME}.\r\n\r\nVous pouvez désormais accéder à vos coordonnées et souscriptions en vous connectant à l''adresse suivante:\r\n\r\n{LOGIN_URI} \r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe: {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)', 'fr_FR', 'Nouvelle adhésion');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (3, 'sub', 'Sus identificaciones', 'Hola,\r\n\r\nAcaba de ser dado de alta en el sistema de gestión de socios de la asociación {NAME}.\r\n\r\nAhora puede seguir en tiempo real el estado de su inscripción y actualizar sus preferencias usando la interfaz web prevista con este fin:\r\n\r\n{LOGIN_URI} \r\n\r\nNombre de usuario: {LOGIN}\r\nContraseña: {PASSWORD}\r\n\r\n¡Hasta pronto!\r\n\r\n(este correo ha sido enviado automáticamente)', 'es_ES', 'Nuevo .....????');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (4, 'pwd', 'Your identifiers', 'Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nTemporary password: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'Lost password email');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (5, 'pwd', 'Vos Identifiants', 'Bonjour,\r\n\r\nQuelqu''un (probablement vous) a demandé la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe Temporaire: {PASSWORD}\r\n\r\nA Bientôt!\r\n\r\n(Courrier envoyé automatiquement)', 'fr_FR', 'Récupération du mot de passe');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (6, 'pwd', 'Sus identificaciones', 'Hola,\r\n\r\nAlguien (probablemente usted) pidió que se le reenviase su contraseña.\r\n\r\nPor favor identifíquese usted en esta dirección para modificar su contraseña:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nContraseña provisional: {PASSWORD}\r\n\r\n¡Hasta pronto!\r\n\r\n(este correo ha sido enviado automáticamente)', 'es_ES', 'Recuperación de la contraseña');
