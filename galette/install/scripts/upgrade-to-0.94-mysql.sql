SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE galette_texts ADD UNIQUE KEY `localizedtxt` (tref, tlang);

UPDATE galette_database SET version = 0.94;
SET FOREIGN_KEY_CHECKS=1;
