-- Table for reminders;
DROP TABLE IF EXISTS reminders;
CREATE TABLE reminders (
  reminder_id INTEGER NOT NULL PRIMARY KEY,
  reminder_type INTEGER NOT NULL,
  reminder_dest INTEGER,
  reminder_date TEXT NOT NULL,
  reminder_success INTEGER NOT NULL default 0,
  reminder_nomail INTEGER NOT NULL default 1,
  reminder_comment TEXT,
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh)
);

UPDATE galette_database SET version = 0.703;
