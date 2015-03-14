ALTER TABLE galette_adherents ADD COLUMN parent_id INTEGER REFERENCES galette_adherents(id_adh) ON DELETE RESTRICT ON UPDATE RESTRICT DEFAULT NULL;

UPDATE galette_database SET version = 0.82;
