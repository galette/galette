-- Table for reminders;
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id INTEGER NOT NULL PRIMARY KEY,
  reminder_type INTEGER NOT NULL,
  reminder_dest INTEGER,
  reminder_date TEXT NOT NULL,
  reminder_success INTEGER NOT NULL default 0,
  reminder_nomail INTEGER NOT NULL default 1,
  reminder_comment TEXT,
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE
);

-- Table for PDF models
DROP TABLE IF EXISTS galette_pdfmodels;
CREATE TABLE galette_pdfmodels (
  model_id INTEGER NOT NULL PRIMARY KEY,
  model_name TEXT NOT NULL,
  model_type INTEGER NOT NULL,
  model_header TEXT,
  model_footer TEXT,
  model_body TEXT,
  model_styles TEXT,
  model_title TEXT,
  model_subtitle TEXT,
  model_parent INTEGER DEFAULT NULL,
  FOREIGN KEY (model_parent) REFERENCES galette_pdfmodels (model_id),
);

UPDATE galette_database SET version = 0.703;
