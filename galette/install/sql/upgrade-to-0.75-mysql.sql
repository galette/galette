-- Table for reminders
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id smallint(6) NOT NULL auto_increment,
  reminder_type int(10) NOT NULL,
  reminder_dest int(10) unsigned,
  reminder_date datetime NOT NULL,
  reminder_success tinyint(1) NOT NULL DEFAULT 0,
  reminder_nomail tinyint(1) NOT NULL DEFAULT 1,
  reminder_comment text,
  PRIMARY KEY (reminder_id),
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

UPDATE galette_database SET version = 0.703;
