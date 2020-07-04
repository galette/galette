CREATE UNIQUE INDEX galette_texts_localizedtxt_idx ON galette_texts (tref, tlang);

-- Table for temporaty links
DROP TABLE IF EXISTS galette_tmplinks;
CREATE TABLE galette_tmplinks(
  hash character varying(60) NOT NULL,
  target smallint NOT NULL,
  id integer NOT NULL,
  creation_date timestamp NOT NULL,
  PRIMARY KEY (target, id)
);

-- Add list config field
ALTER TABLE galette_fields_config ADD COLUMN list_visible boolean NOT NULL DEFAULT false;
ALTER TABLE galette_fields_config ADD COLUMN list_position integer NULL DEFAULT -1;

UPDATE galette_fields_config SET list_visible = true, list_position = 0 WHERE field_id = 'id_adh';
UPDATE galette_fields_config SET list_visible = true, list_position = 2 WHERE field_id = 'pseudo_adh';
UPDATE galette_fields_config SET list_visible = true, list_position = 3 WHERE field_id = 'id_statut';
UPDATE galette_fields_config SET list_visible = true, list_position = 5 WHERE field_id = 'date_modif_adh';

UPDATE galette_database SET version = 0.94;
