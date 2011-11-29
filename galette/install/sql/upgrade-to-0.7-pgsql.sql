-- Each preference must be unique
CREATE UNIQUE INDEX galette_preferences_name ON galette_preferences (nom_pref);

-- Add new or missing preferences;
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_slogan', '');
UPDATE galette_preferences SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_preferences SET pref_lang='en_EN' WHERE pref_lang='english';
UPDATE galette_preferences SET pref_lang='es_ES' WHERE pref_lang='spanish';
UPDATE galette_adherents SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_adherents SET pref_lang='en_EN' WHERE pref_lang='english';
UPDATE galette_adherents SET pref_lang='es_ES' WHERE pref_lang='spanish';
ALTER TABLE galette_adherents ALTER pref_lang SET DEFAULT 'fr_FR';
UPDATE galette_preferences SET nom_pref='pref_mail_smtp_host' WHERE nom_pref='pref_mail_smtp';
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
INSERT INTO galette_preferences(nom_pref, val_pref) VALUES ('pref_theme', 'default');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_auth', 'false');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_secure', 'false');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_port', '25');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_user', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_password', '');

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

--
-- Contenu de la table `galette_texts`
--
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES
(1, 'sub', 'Your identifiers', 'Hello,\r\n\r\nYou''ve just been subscribed on the members management system of {NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'New user registration');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (2, 'sub', 'Votre adhésion', 'Bonjour,\r\n\r\nVous venez d''adhérer à {NAME}.\r\n\r\nVous pouvez désormais accéder à vos coordonnées et souscriptions en vous connectant à l''adresse suivante:\r\n\r\n{LOGIN_URI} \r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe: {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)', 'fr_FR', 'Nouvelle adhésion');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (4, 'pwd', 'Your identifiers', 'Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nTemporary password: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'Lost password email');
INSERT INTO galette_texts (tid, tref, tsubject, tbody, tlang, tcomment) VALUES (5, 'pwd', 'Vos Identifiants', 'Bonjour,\r\n\r\nQuelqu''un (probablement vous) a demandé la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe Temporaire: {PASSWORD}\r\n\r\nA Bientôt!\r\n\r\n(Courrier envoyé automatiquement)', 'fr_FR', 'Récupération du mot de passe');

-- Add missing primary keys
CREATE UNIQUE INDEX galette_dynamic_fields_unique_idx ON galette_dynamic_fields (item_id, field_id, field_form, val_index);

-- New table for fields categories
DROP TABLE galette_fields_categories CASCADE;
CREATE TABLE galette_fields_categories (
  id_field_category integer  DEFAULT nextval('galette_fields_categories_id_seq'::text) NOT NULL,
  category character varying(50) NOT NULL,
  position integer NOT NULL,
  PRIMARY KEY (id_field_category)
);
CREATE UNIQUE INDEX galette_fields_categories_idx ON galette_fields_categories (id_field_category);

-- Base fields categories
INSERT INTO galette_fields_categories (id_field_category, category, position) VALUES (1, 'Identity:', 1);
INSERT INTO galette_fields_categories (id_field_category, category, position) VALUES (2, 'Galette-related data:', 2);
INSERT INTO galette_fields_categories (id_field_category, category, position) VALUES (3, 'Contact information:', 3);

-- New table for fields configuration
DROP TABLE galette_fields_config;
CREATE TABLE galette_fields_config (
  table_name character varying(30) NOT NULL,
  field_id character varying(30) NOT NULL,
  required character(1) NOT NULL,
  visible character(1) NOT NULL,
  position integer NOT NULL,
  id_field_category integer REFERENCES galette_fields_categories ON DELETE RESTRICT
);

-- Table for mailing history storage
DROP TABLE galette_mailing_history;
CREATE TABLE galette_mailing_history (
  mailing_id integer DEFAULT nextval('galette_mailing_history_id_seq'::text) NOT NULL,
  mailing_sender integer REFERENCES galette_adherents ON DELETE RESTRICT ON UPDATE CASCADE,
  mailing_subjectf character varying(255) NOT NULL,
  mailing_body text NOT NULL,
  mailing_date timestamp NOT NULL,
  mailing_recipients text NOT_NULL
  mailing sent character(1) DEFAULT NULL
);
CREATE UNIQUE INDEX galette_mailing_history_idx ON galette_mailing_history (mailing_id);

ALTER TABLE galette_adherents ADD type_paiement_cotis smallint DEFAULT '0' NOT NULL;

-- Missing primary keys
ALTER TABLE galette_types_cotisation ADD CONSTRAINT galette_types_cotisation_pkey PRIMARY KEY (id_type_cotis);
ALTER TABLE galette_adherents ADD CONSTRAINT galette_adherents_pkey PRIMARY KEY (id_adh);