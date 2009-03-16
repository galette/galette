-- We remove datas if present
DELETE FROM `galette_preferences`;
DELETE FROM `galette_types_cotisation`;
DELETE FROM `galette_statuts`;
DELETE FROM `galette_texts`;

-- Base data for fresh install

INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_admin_login', 'admin');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_admin_pass', '21232f297a57a5a743894a0e4a801fc3');

INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_nom','galette');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_slogan','');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_adresse','-');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_adresse2','');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_cp','-');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_ville','-');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_pays','-');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_lang','fr_FR');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_numrows','30');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_log','2');

INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_email_nom','galette');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_email','mail@domain.com');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_mail_method','0');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_mail_smtp','');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_membership_ext','12');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_beg_membership','');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_email_reply_to','');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_website','');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_email_newadh', 'mail@domain.com');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_bool_mailadh', '0');

INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_marges_v','10');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_marges_h','10');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_hspace','10');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_vspace','5');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_hsize','90');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_vsize','35');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_cols','2');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_rows','7');
INSERT INTO `galette_preferences` (`nom_pref`,`val_pref`) VALUES ('pref_etiq_corps','12');

-- Add card preferences;
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_abrev', 'GALETTE');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_strip','Gestion d Adherents en Ligne Extrêmement Tarabiscoté');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_tcol', 'FFFFFF');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_scol', '8C2453');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_bcol', '53248C');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_hcol', '248C53');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_bool_display_title', '');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_address', '1');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_year', '2007');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_marges_v', '15');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_marges_h', '20');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_vspace', '5');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_hspace', '10');
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_card_self', '1');

-- Default theme
INSERT INTO `galette_preferences` (`nom_pref`, `val_pref`) VALUES ('pref_theme', 'default');

-- Contribution types
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (1, 'annual fee', '1');
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (2, 'reduced annual fee', '1');
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (3, 'company fee', '1');
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (4, 'donation in kind', null);
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (5, 'donation in money', null);
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (6, 'partnership', null);
INSERT INTO `galette_types_cotisation` (`id_type_cotis`,`libelle_type_cotis`,`cotis_extension`) VALUES (7, 'annual fee (to be paid)', '1');

-- Member types
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (1, 'President',0);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (2, 'Treasurer',10);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (3, 'Secretary',20);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (4, 'Active member',30);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (5, 'Benefactor member',40);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (6, 'Founder member',50);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (7, 'Old-timer',60);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (8, 'Society',70);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (9, 'Non-member',80);
INSERT INTO `galette_statuts` (`id_statut`,`libelle_statut`,`priorite_statut`) VALUES (10, 'Vice-president',5);

-- Emails texts
INSERT INTO `galette_texts` (`tid`, `tref`, `tsubject`, `tbody`, `tlang`, `tcomment`) VALUES 
(1, 'sub', 'Your identifiers', 'Hello,\r\n\r\nYou''ve just been subscribed on the members management system of {NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'New user registration'),
(2, 'sub', 'Votre adhésion', 'Bonjour,\r\n\r\nVous venez d''adhérer à {NAME}.\r\n\r\nVous pouvez désormais accèder à vos coordonées et souscriptions en vous connectant à l''adresse suivante:\r\n\r\n{LOGIN_URI} \r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe: {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)', 'fr_FR', 'Nouvelle adhésion'),
(3, 'sub', 'Sus identificaciones', 'Hola,\r\n\r\nAcaba de ser dado de alta en el sistema de gestión de socios de la asociación {NAME}.\r\n\r\nAhora puede seguir en tiempo real el estado de su inscripción y actualizar sus preferencias usando la interfaz web prevista con este fin:\r\n\r\n{LOGIN_URI} \r\n\r\nNombre de usuario: {LOGIN}\r\nContraseña: {PASSWORD}\r\n\r\n¡Hasta pronto!\r\n\r\n(este correo ha sido enviado automáticamente)', 'es_ES', 'Nueva inscripción'),
(4, 'pwd', 'Your identifiers', 'Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nTemporary password: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)', 'en_EN', 'Lost password email'),
(5, 'pwd', 'Vos Identifiants', 'Bonjour,\r\n\r\nQuelqu''un (probablement vous) a demander la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nMot de passe Temporaire: {PASSWORD}\r\n\r\nA Bientôt!\r\n\r\n(Courrier envoyé automatiquement)', 'fr_FR', 'Récupération du mot de passe'),
(6, 'pwd', 'Sus identificaciones', 'Hola,\r\n\r\nAlguien (probablemente usted) pidió que se le reenviase su contraseña.\r\n\r\nPor favor identifíquese usted en esta dirección para modificar su contraseña:\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant: {LOGIN}\r\nContraseña provisional: {PASSWORD}\r\n\r\n¡Hasta pronto!\r\n\r\n(este correo ha sido enviado automáticamente)', 'es_ES', 'Recuperación de la contraseña'),
(7, 'contrib', 'Votre cotisation', 'Votre cotisation à {NAME} a été enregistrée et validée par l''association.\r\n\r\nElle est valable jusqu''au {DEADLINE}\r\n\r\nVous pouvez désormais accèder à vos données personnelles à l''aide de vos identifiants galette.\r\n\r\n{COMMENT}', 'fr_FR', 'Accusé de réception de cotisation'),
(8, 'contrib', 'Your contribution', 'Your contribution has succefully been taken into account by {NAME}.\r\n\r\nIt is valid until {DEADLINE}.\r\n\r\nYou can now login and browse or modify your personnal data using your galette identifiers.\r\n\r\n{COMMENT}', 'en_EN', 'Receipt send for every new contribution'),
(9, 'contrib', 'Su contribution', 'Association: {NAME} (not translated)\r\nDeadline: {DEADLINE} (not translated)\r\nComment: \r\n{COMMENT}', 'es_ES', '(not translated)'),
(10, 'newadh', 'New registration from  {SURNAME_ADH} {NAME_ADH}', '{SURNAME_ADH} {NAME_ADH} has registred on line with login: {LOGIN}', 'en_EN', 'New registration => sent to admin'),
(11, 'newadh', 'Nouvelle inscription de {SURNAME_ADH} {NAME_ADH}', '{SURNAME_ADH} {NAME_ADH} s''est incrit via l''interface web avec le login {LOGIN}', 'fr_FR', 'Nouvelle inscription => envoyée a l''admin'),
(12, 'newadh', 'Nueva inscripción de  {SURNAME_ADH} {NAME_ADH}', '{SURNAME_ADH} {NAME_ADH} has registred on line with login: {LOGIN}', 'es_ES', 'Nueva inscripción => sent to admin'),
(13, 'newcont', 'New contributio for  {SURNAME_ADH} {NAME_ADH}', 'The contribution from {SURNAME_ADH} {NAME_ADH} has been registered (new deadline: {DEADLINE})\r\n\r\n{COMMENT}', 'en_EN', 'New contribution => sent to admin'),
(14, 'newcont', 'Nouvelle contribution de {SURNAME_ADH} {NAME_ADH}', 'La contribution de {SURNAME_ADH} {NAME_ADH} a été enregistrée (nouvelle échéance: {DEADLINE})\r\n\r\n{COMMENT}', 'fr_FR', 'Nouvelle contribution => envoyée a l''admin'),
(15, 'newcont', 'Nueva contribution de  {SURNAME_ADH} {NAME_ADH}', '(not translated) The contribution from {SURNAME_ADH} {NAME_ADH} has been registered (new deadline: {DEADLINE})\r\n\r\n{COMMENT}', 'es_ES', 'Nueva contribution=> sent to admin');
