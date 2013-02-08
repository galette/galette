-- sequence for reminders
DROP SEQUENCE IF EXISTS galette_reminders_id_seq;
CREATE SEQUENCE galette_reminders_id_seq
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
  reminder_dest integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  reminder_date timestamp NOT NULL,
  reminder_success boolean DEFAULT FALSE,
  reminder_nomail boolean DEFAULT TRUE,
  reminder_comment text,
  PRIMARY KEY (reminder_id)
);

UPDATE galette_database SET version = 0.703;
