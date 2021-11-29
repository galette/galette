CREATE TABLE galette_socials (
     id_social int(10) unsigned NOT NULL auto_increment,
     id_adh int(10) unsigned NULL,
     type varchar(250) NOT NULL,
     url varchar(255) DEFAULT NULL,
     PRIMARY KEY (id_social),
     KEY (type),
     FOREIGN KEY (id_adh) REFERENCES  galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- migrate socials from preferences
INSERT INTO galette_socials (id_adh, type, url) SELECT null, 'google+', val_pref FROM galette_preferences WHERE nom_pref = 'pref_googleplus' AND val_pref != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT null, 'facebook', val_pref FROM galette_preferences WHERE nom_pref = 'pref_facebook' AND val_pref != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT null, 'twitter', val_pref FROM galette_preferences WHERE nom_pref = 'pref_twitter' AND val_pref != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT null, 'linkedin', val_pref FROM galette_preferences WHERE nom_pref = 'pref_linkedin' AND val_pref != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT null, 'viadeo', val_pref FROM galette_preferences WHERE nom_pref = 'pref_viadeo' AND val_pref != '';
-- cleanup preferences
DELETE FROM galette_preferences WHERE
    nom_pref = 'pref_googleplus'
    OR nom_pref = 'pref_facebook'
    OR nom_pref = 'pref_twitter'
    OR nom_pref = 'pref_linkedin'
    OR nom_pref = 'pref_viadeo';
-- update pdf card address
UPDATE galette_preferences SET val_pref = 0 WHERE nom_pref = 'pref_card_address' AND val_pref IN ('1', '2', '3', '4');

-- migrate members socials
INSERT INTO galette_socials (id_adh, type, url) SELECT id_adh, 'website', url_adh FROM galette_adherents WHERE url_adh != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT id_adh, 'icq', icq_adh FROM galette_adherents WHERE icq_adh != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT id_adh, 'msn', msn_adh FROM galette_adherents WHERE msn_adh != '';
INSERT INTO galette_socials (id_adh, type, url) SELECT id_adh, 'jabber', jabber_adh FROM galette_adherents WHERE jabber_adh != '';

-- drop adresse2_adh field
UPDATE galette_adherents SET adresse2_adh = NULL WHERE adresse2_adh = '';
UPDATE galette_adherents SET adresse_adh = CONCAT_WS("\n", adresse_adh, adresse2_adh);

-- cleanup members table
ALTER TABLE galette_adherents DROP column url_adh;
ALTER TABLE galette_adherents DROP column icq_adh;
ALTER TABLE galette_adherents DROP column msn_adh;
ALTER TABLE galette_adherents DROP column jabber_adh;
ALTER TABLE galette_adherents DROP column adresse2_adh;

-- cleanup fields config
DELETE FROM galette_fields_config WHERE field_id IN ('url_adh', 'icq_adh', 'msn_adh', 'jabber_adh', 'adresse2_adh');


-- add num_adh column
ALTER TABLE galette_adherents ADD COLUMN num_adh varchar(255) DEFAULT NULL;

ALTER TABLE  galette_searches DROP INDEX form;
ALTER TABLE galette_searches DROP COLUMN parameters_sum;

-- drop groups unique name constraint
ALTER TABLE galette_groups DROP INDEX `name`;

-- add information on dynamic fields
ALTER TABLE galette_field_types ADD COLUMN field_information text DEFAULT NULL;
-- field that has never been used
ALTER TABLE galette_field_types DROP COLUMN field_layout;

UPDATE galette_database SET version = 0.960;