ALTER TABLE adherents RENAME TO galette_adherents;
ALTER TABLE cotisations RENAME TO galette_cotisations;
ALTER TABLE logs RENAME TO galette_logs;
ALTER TABLE preferences RENAME TO galette_preferences;
ALTER TABLE statuts RENAME TO galette_statuts;
ALTER TABLE types_cotisation RENAME TO galette_types_cotisation;

DROP SEQUENCE galette_preferences_id_seq;
CREATE SEQUENCE galette_preferences_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;
		
DROP TABLE galette_preferences;
CREATE TABLE galette_preferences (
  id_pref integer DEFAULT nextval('galette_preferences_id_seq'::text) NOT NULL,
  nom_pref varying(100) DEFAULT '' NOT NULL,
  val_pref varying(200) DEFAULT '' NOT NULL
);
