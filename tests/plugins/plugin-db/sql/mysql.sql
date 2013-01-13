DROP TABLE IF EXISTS galette_logs;
CREATE TABLE galette_logs (
  id int(10) unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  comment text,
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
