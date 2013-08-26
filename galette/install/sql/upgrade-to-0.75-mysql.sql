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
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS galette_pdfmodels;

CREATE TABLE galette_pdfmodels (
  model_id int(10) unsigned NOT NULL auto_increment,
  model_name varchar(50) NOT NULL,
  model_type tinyint(2) NOT NULL,
  model_header text,
  model_footer text,
  model_body text,
  model_styles text,
  model_title varchar(100),
  model_subtitle varchar(100),
  model_parent int(10) unsigned DEFAULT NULL REFERENCES galette_pdfmodels (model_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

ALTER TABLE galette_tmppasswds DROP FOREIGN KEY galette_tmppasswds_ibfk_1;
ALTER TABLE galette_tmppasswds
  ADD CONSTRAINT galette_tmppasswds_ibfk_1
    FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE RESTRICT ;

UPDATE galette_database SET version = 0.703;
