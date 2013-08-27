-- sequence for import model
DROP SEQUENCE IF EXISTS galette_import_model_id_seq;
CREATE SEQUENCE galette_import_model_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id integer DEFAULT nextval('galette_import_model_id_seq'::text) NOT NULL,
  model_fields text,
  model_creation_date timestamp NOT NULL,
  PRIMARY KEY (model_id)
);

UPDATE galette_database SET version = 0.704;
