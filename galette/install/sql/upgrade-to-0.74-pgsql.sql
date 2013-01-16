ALTER TABLE galette_adherents ALTER COLUMN mdp_adh TYPE character varying(60);

UPDATE galette_database SET version = 0.702;
