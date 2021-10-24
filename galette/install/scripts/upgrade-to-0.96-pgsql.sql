-- sequence for socials
CREATE SEQUENCE galette_socials_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

CREATE TABLE galette_socials (
 id_social integer DEFAULT nextval('galette_socials_id_seq'::text) NOT NULL,
 id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
 type character varying(250) NOT NULL,
 url character varying(255) DEFAULT NULL,
 PRIMARY KEY (id_social)
);
-- add index on table to look for type
CREATE INDEX galette_socials_idx ON galette_socials (type);

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
-- cleanup members table
ALTER TABLE galette_adherents DROP column url_adh;
ALTER TABLE galette_adherents DROP column icq_adh;
ALTER TABLE galette_adherents DROP column msn_adh;
ALTER TABLE galette_adherents DROP column jabber_adh;
-- cleanup fields config table
DELETE FROM galette_fields_config WHERE field_id IN ('url_adh', 'icq_adh', 'msn_adh', 'jabber_adh');

UPDATE galette_database SET version = 0.960;