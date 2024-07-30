DROP SEQUENCE IF EXISTS galette_db_test_id_seq;
CREATE SEQUENCE galette_db_test_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_db_test;
CREATE TABLE galette_db_test (
  id integer DEFAULT nextval('galette_db_test_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  comment text,
  PRIMARY KEY (id)
);

