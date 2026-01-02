CREATE TABLE galette_plugins (
  plugin_id character varying(100) NOT NULL,
  version decimal DEFAULT NULL,
  PRIMARY KEY (plugin_id)
);

UPDATE galette_database SET version = 1.220;
