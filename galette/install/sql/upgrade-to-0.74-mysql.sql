ALTER TABLE galette_adherents CHANGE mdp_adh mdp_adh VARCHAR( 60 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''

UPDATE galette_database SET version = 0.702;
