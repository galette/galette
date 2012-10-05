UPDATE galette_adherents SET pseudo_adh = '' WHERE pseudo_adh IS NULL;
UPDATE galette_adherents SET prenom_adh = '' WHERE prenom_adh IS NULL;

-- Update fields length
ALTER TABLE galette_adherents ALTER COLUMN nom_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh SET DEFAULT '';
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh SET NOT NULL;
ALTER TABLE galette_adherents ALTER COLUMN societe_adh TYPE varchar(200);
ALTER TABLE galette_transactions ALTER COLUMN trans_desc TYPE varchar(150);
ALTER TABLE galette_adherents ALTER COLUMN pseudo_adh SET DEFAULT '';
ALTER TABLE galette_adherents ALTER COLUMN pseudo_adh SET NOT NULL;

ALTER TABLE galette_field_types ALTER COLUMN field_repeat DROP DEFAULT;
ALTER TABLE galette_field_types ALTER field_repeat TYPE integer USING CASE WHEN field_repeat=false THEN NULL ELSE 0 END;
ALTER TABLE galette_field_types ALTER COLUMN field_repeat SET DEFAULT NULL;

UPDATE galette_database SET version = 0.701;
