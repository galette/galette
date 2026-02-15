ALTER TABLE galette_field_types ADD COLUMN field_specifications JSON DEFAULT NULL;

ALTER TABLE galette_types_cotisation ADD COLUMN description text;
UPDATE galette_types_cotisation SET description = '' WHERE description IS NULL;
ALTER TABLE galette_types_cotisation ALTER COLUMN description SET NOT NULL;

CREATE TABLE galette_plugins (
  plugin_id character varying(100) NOT NULL,
  version decimal DEFAULT NULL,
  PRIMARY KEY (plugin_id)
);
