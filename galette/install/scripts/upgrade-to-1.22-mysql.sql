CREATE TABLE galette_plugins (
  plugin_id varchar(100) NOT NULL,
  version DECIMAL(4,3) NULL DEFAULT NULL,
  PRIMARY KEY (plugin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

UPDATE galette_database SET version = 1.220;