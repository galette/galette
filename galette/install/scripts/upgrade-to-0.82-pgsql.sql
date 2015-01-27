-- Families
ALTER TABLE galette_adherents ADD parent_id integer;
ALTER TABLE galette_adherents ALTER COLUMN parent_id SET DEFAULT NULL;
ALTER TABLE galette_adherents ADD CONSTRAINT galette_adherents_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES galette_adherents(id_adh);

UPDATE galette_database SET version = 0.82;
