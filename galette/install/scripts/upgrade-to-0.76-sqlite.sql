-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id INTEGER NOT NULL PRIMARY KEY,
  model_fields TEXT,
  model_creation_date TEXT NOT NULL
);

UPDATE galette_database SET version = 0.704;
