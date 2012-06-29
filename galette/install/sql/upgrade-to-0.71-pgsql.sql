
-- Update fields length
ALTER TABLE galette063_adherents ALTER COLUMN nom_adh TYPE varchar(50);
ALTER TABLE galette063_adherents ALTER COLUMN prenom_adh TYPE varchar(50);
ALTER TABLE galette063_adherents ALTER COLUMN societe_adh TYPE varchar(200);
ALTER TABLE galette063_transactions ALTER COLUMN trans_desc TYPE varchar(150);

UPDATE galette_database SET version = 0.701;
