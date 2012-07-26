
-- Update fields length
ALTER TABLE galette_adherents ALTER COLUMN nom_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN societe_adh TYPE varchar(200);
ALTER TABLE galette_transactions ALTER COLUMN trans_desc TYPE varchar(150);

ALTER TABLE galette_field_types ALTER COLUMN field_required DROP DEFAULT;
ALTER TABLE galette_field_types ALTER field_required TYPE integer USING CASE WHEN field_required=false THEN NULL ELSE 0 END;
ALTER TABLE galette_field_types ALTER COLUMN field_required SET DEFAULT NULL;

UPDATE galette_database SET version = 0.701;
