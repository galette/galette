-- sequence for reminders
DROP SEQUENCE IF EXISTS galette_reminders_id_seq;
CREATE SEQUENCE galette_reminders_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for pdf models
DROP SEQUENCE IF EXISTS galette_pdfmodels_id_seq;
CREATE SEQUENCE galette_pdfmodels_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for reminders
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id integer DEFAULT nextval('galette_reminders_id_seq'::text) NOT NULL,
  reminder_type integer NOT NULL,
  reminder_dest integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  reminder_date timestamp NOT NULL,
  reminder_success boolean DEFAULT FALSE,
  reminder_nomail boolean DEFAULT TRUE,
  reminder_comment text,
  PRIMARY KEY (reminder_id)
);
  
DROP TABLE IF EXISTS galette_pdfmodels CASCADE;
CREATE TABLE galette_pdfmodels (
  model_id integer DEFAULT nextval('galette_pdfmodels_id_seq'::text) NOT NULL,
  model_name character varying(50) NOT NULL,
  model_type integer NOT NULL,
  model_header text,
  model_footer text,
  model_body text,
  model_styles text,
  model_title character varying(100),
  model_subtitle character varying(100),
  model_parent integer DEFAULT NULL REFERENCES galette_pdfmodels (model_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (model_id)
);

ALTER TABLE galette_tmppasswds
  ADD CONSTRAINT galette_tmppasswds_id_adh_fkey
    FOREIGN KEY (id_adh) REFERENCES galette_adherents ON DELETE CASCADE;

UPDATE galette_database SET version = 0.703;
