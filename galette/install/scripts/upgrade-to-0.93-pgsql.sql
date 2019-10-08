-- sequence for searches
DROP SEQUENCE IF EXISTS galette_searches_id_seq;
CREATE SEQUENCE galette_searches_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for saved searches
DROP TABLE IF EXISTS galette_searches;
CREATE TABLE galette_searches (
  search_id integer DEFAULT nextval('galette_searches_id_seq'::text) NOT NULL,
  name character varying(100) DEFAULT NULL,
  form character varying(50) NOT NULL,
  parameters jsonb NOT NULL,
  parameters_sum bytea NOT NULL,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  creation_date timestamp NOT NULL,
  PRIMARY KEY (search_id)
);
-- add unicity on searches
CREATE INDEX galette_searches_idx ON galette_searches (form, parameters_sum, id_adh);

UPDATE galette_fields_categories SET category = 'Identity:' WHERE id_field_category = 1;
UPDATE galette_fields_categories SET category = 'Galette-related data:' WHERE id_field_category = 2;
UPDATE galette_fields_categories SET category = 'Contact information:' WHERE id_field_category = 3;

UPDATE galette_database SET version = 0.93;
