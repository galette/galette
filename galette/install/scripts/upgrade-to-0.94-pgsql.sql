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
ALTER TABLE galette_fields_config ADD COLUMN list_position integer NULL DEFAULT NULL;

UPDATE galette_database SET version = 0.94;
