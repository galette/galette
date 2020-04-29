CREATE UNIQUE INDEX galette_texts_localizedtxt_idx ON galette_texts (tref, tlang);

UPDATE galette_database SET version = 0.94;
